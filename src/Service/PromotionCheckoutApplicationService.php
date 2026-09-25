<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionCheckoutApplicationResultDTO;
use App\Promoting\DTO\PromotionCheckoutPlanResultDTO;
use App\Promoting\DTO\PromotionCheckoutReversalResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionCampaignServiceInterface;
use App\Promoting\ServiceInterface\PromotionCampaignUsageServiceInterface;
use App\Promoting\ServiceInterface\PromotionCheckoutApplicationServiceInterface;
use App\Promoting\ServiceInterface\PromotionCheckoutPlanServiceInterface;
use App\Promoting\ServiceInterface\PromotionCouponServiceInterface;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignSpend;
use App\Promoting\ValueObject\PromotionCampaignSpendLedger;
use App\Promoting\ValueObject\PromotionCampaignUsageLedger;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Applies a unified checkout plan and records coupon redemption only when its promotion actually participates. */
final readonly class PromotionCheckoutApplicationService implements PromotionCheckoutApplicationServiceInterface
{
    public function __construct(
        private PromotionCheckoutPlanServiceInterface $planService,
        private PromotionCouponServiceInterface $couponService,
        private PromotionCampaignServiceInterface $campaignService,
        private PromotionCampaignUsageServiceInterface $usageService,
    ) {
    }

    /** Applies coupon and optional campaign effects only after one unified checkout resolution. */
    public function apply(
        PromotionCatalog $catalog,
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $ledger,
        PromotionEvaluationRequestDTO $request,
        PromotionBenefitRequestDTO $benefitRequest,
        ?string $couponCode = null,
        ?string $customerId = null,
        ?string $orderId = null,
        ?PromotionCampaign $campaign = null,
        ?PromotionCampaignSpendLedger $campaignSpendLedger = null,
        ?PromotionCampaignUsageLedger $campaignUsageLedger = null,
    ): PromotionCheckoutApplicationResultDTO {
        $planningLedger = $ledger;
        $planningCampaign = $campaign;
        $existingCouponRedemption = null;
        $existingCampaignSpend = null;
        $campaignUsageRejected = false;

        if (null !== $couponCode) {
            if (null === $customerId || '' === trim($customerId)) {
                throw new \InvalidArgumentException('Customer id is required when a coupon code is provided.');
            }

            $couponOrderId = $this->requireOrderId($orderId);
            $existingCouponRedemption = $ledger->findActive($couponCode, $customerId, $couponOrderId);
            if (null !== $existingCouponRedemption) {
                $planningLedger = $ledger->reverse($couponCode, $customerId, $couponOrderId);
            }
        }

        if (null !== $campaign) {
            $campaignOrderId = $this->requireOrderId($orderId);
            if (null === $campaignSpendLedger) {
                throw new \InvalidArgumentException('Campaign spend ledger is required when a campaign is provided.');
            }
            if ($campaign->spentMinor !== $campaignSpendLedger->activeSpendForCampaign($campaign->id)) {
                throw new \DomainException('Campaign spend aggregate does not match campaign spend ledger.');
            }
            if (null !== $campaign->applicationLimit && null === $campaignUsageLedger) {
                throw new \InvalidArgumentException('Campaign usage ledger is required when an application limit is configured.');
            }

            $existingCampaignSpend = $campaignSpendLedger->findActive($campaign->id, $campaignOrderId);
            if (
                null !== $existingCampaignSpend
                && null !== $campaignUsageLedger
                && null === $campaignUsageLedger->findActive($campaign->id, $campaignOrderId)
            ) {
                throw new \DomainException('Campaign spend replay is missing its campaign usage record.');
            }
            if (null !== $existingCampaignSpend) {
                $planningCampaign = $this->campaignService->releaseSpend(
                    $campaign,
                    $existingCampaignSpend->amountMinor,
                );
            }

            if (null !== $campaignUsageLedger) {
                $usageValidation = $this->usageService->validate(
                    $campaign,
                    $campaignUsageLedger,
                    $campaignOrderId,
                );
                if (!$usageValidation->allowed) {
                    $campaignUsageRejected = true;
                    $planningCampaign = null;
                }
            }
        } elseif (null !== $campaignSpendLedger || null !== $campaignUsageLedger) {
            throw new \InvalidArgumentException('Campaign context is required when a campaign ledger is provided.');
        }

        $plan = $this->planService->plan(
            $catalog,
            $couponBook,
            $planningLedger,
            $request,
            $benefitRequest,
            $couponCode,
            $customerId,
            $planningCampaign,
        );

        if ($campaignUsageRejected) {
            $plan = new PromotionCheckoutPlanResultDTO(
                $plan->resolution,
                $plan->couponResolution,
                $plan->campaignSelection,
                $plan->benefits,
                [...$plan->reasons, 'checkout_campaign_application_limit_reached'],
            );
        }

        $campaignDiscountAmountMinor = $this->campaignDiscountAmount($plan);
        if (
            null !== $existingCampaignSpend
            && $existingCampaignSpend->amountMinor !== $campaignDiscountAmountMinor
        ) {
            throw new \DomainException('Campaign replay spend does not match the current checkout plan.');
        }

        $campaignBudgetRejected = false;
        if (
            null !== $planningCampaign
            && null !== $planningCampaign->budgetMinor
            && $planningCampaign->spentMinor + $campaignDiscountAmountMinor > $planningCampaign->budgetMinor
        ) {
            $campaignPlan = $plan;
            $fallback = $this->planService->plan(
                $catalog,
                $couponBook,
                $planningLedger,
                $request,
                $benefitRequest,
                $couponCode,
                $customerId,
            );
            $plan = new PromotionCheckoutPlanResultDTO(
                $fallback->resolution,
                $fallback->couponResolution,
                $campaignPlan->campaignSelection,
                $fallback->benefits,
                [...$fallback->reasons, 'checkout_campaign_budget_would_exceed'],
            );
            $campaignDiscountAmountMinor = 0;
            $campaignBudgetRejected = true;
        }

        $couponRedemption = null;
        $resultLedger = $ledger;
        $reasons = [];

        if (null === $couponCode || null === $plan->couponResolution) {
            $reasons[] = 'checkout_application_completed_without_coupon';
        } else {
            $couponResolution = $plan->couponResolution;
            if (
                !$couponResolution->eligible
                || null === $couponResolution->coupon
                || null === $couponResolution->promotion
            ) {
                if (null !== $existingCouponRedemption) {
                    throw new \DomainException('Coupon replay no longer matches the current checkout plan.');
                }
                $reasons = [...$reasons, ...$couponResolution->reasons, 'checkout_coupon_not_redeemed'];
            } elseif (!$this->promotionApplied($plan, $couponResolution->promotion->id)) {
                if (null !== $existingCouponRedemption) {
                    throw new \DomainException('Coupon replay no longer matches the current checkout plan.');
                }
                $reasons[] = 'checkout_coupon_not_reached_by_resolution';
            } else {
                $redemption = $this->couponService->redeem(
                    $couponResolution->coupon,
                    $ledger,
                    $customerId,
                    $this->requireOrderId($orderId),
                    $request->at,
                );
                $couponRedemption = $redemption;
                $resultLedger = $redemption->ledger;
                $reasons = [
                    ...$reasons,
                    ...$redemption->reasons,
                    $redemption->redeemed
                        ? 'checkout_coupon_redeemed'
                        : 'checkout_coupon_redemption_failed',
                ];
            }
        }

        $resultCampaign = $campaign;
        $resultCampaignSpendLedger = $campaignSpendLedger;
        $resultCampaignUsageLedger = $campaignUsageLedger;
        $campaignSpend = null;

        if (null !== $campaign) {
            $campaignParticipated = $this->campaignParticipated($plan);
            if ($campaignUsageRejected) {
                $reasons[] = 'checkout_campaign_usage_not_recorded_limit';
            } elseif ($campaignParticipated && null !== $campaignUsageLedger) {
                $usageMutation = $this->usageService->record(
                    $campaign,
                    $campaignUsageLedger,
                    $this->requireOrderId($orderId),
                );
                $resultCampaignUsageLedger = $usageMutation->ledger;
                $reasons = [...$reasons, ...$usageMutation->reasons];
            }

            if ($campaignBudgetRejected) {
                $reasons[] = 'checkout_campaign_spend_not_recorded_budget';
            } elseif (null === $plan->campaignSelection || !$plan->campaignSelection->available) {
                $reasons[] = 'checkout_campaign_not_applied';
            } elseif ($campaignDiscountAmountMinor < 1) {
                $reasons[] = 'checkout_campaign_no_monetary_spend';
            } elseif (null !== $existingCampaignSpend) {
                $campaignSpend = $existingCampaignSpend;
                $reasons[] = 'campaign_spend_idempotent_replay';
            } else {
                $campaignSpend = new PromotionCampaignSpend(
                    $campaign->id,
                    $this->requireOrderId($orderId),
                    $campaignDiscountAmountMinor,
                );
                $resultCampaign = $this->campaignService->recordSpend($campaign, $campaignDiscountAmountMinor);
                $resultCampaignSpendLedger = $campaignSpendLedger->record($campaignSpend);
                $reasons[] = 'checkout_campaign_spend_recorded';
            }
        }

        return new PromotionCheckoutApplicationResultDTO(
            $plan,
            $resultLedger,
            $couponRedemption,
            $reasons,
            $resultCampaign,
            $resultCampaignSpendLedger,
            $campaignSpend,
            $resultCampaignUsageLedger,
        );
    }

    private function requireOrderId(?string $orderId): string
    {
        if (null === $orderId || '' === trim($orderId)) {
            throw new \InvalidArgumentException('Order id is required when coupon or campaign context is provided.');
        }

        return $orderId;
    }

    private function promotionApplied(PromotionCheckoutPlanResultDTO $plan, string $promotionId): bool
    {
        foreach ($plan->resolution->applications as $application) {
            if ($application->eligible && $application->promotionId === $promotionId) {
                return true;
            }
        }

        return false;
    }

    private function campaignParticipated(PromotionCheckoutPlanResultDTO $plan): bool
    {
        if (null === $plan->campaignSelection || !$plan->campaignSelection->available) {
            return false;
        }

        $promotionIds = [];
        foreach ($plan->campaignSelection->promotions as $promotion) {
            $promotionIds[$promotion->id] = true;
        }

        foreach ($plan->resolution->applications as $application) {
            if ($application->eligible && isset($promotionIds[$application->promotionId])) {
                return true;
            }
        }

        return false;
    }

    private function campaignDiscountAmount(PromotionCheckoutPlanResultDTO $plan): int
    {
        if (null === $plan->campaignSelection || !$plan->campaignSelection->available) {
            return 0;
        }

        $promotionIds = [];
        foreach ($plan->campaignSelection->promotions as $promotion) {
            $promotionIds[$promotion->id] = true;
        }

        $amountMinor = 0;
        foreach ($plan->resolution->applications as $application) {
            if ($application->eligible && isset($promotionIds[$application->promotionId])) {
                $amountMinor += $application->discountAmountMinor;
            }
        }

        return $amountMinor;
    }

    public function reverseCoupon(
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $ledger,
        string $couponCode,
        string $customerId,
        string $orderId,
    ): PromotionCheckoutReversalResultDTO {
        if ('' === trim($couponCode)) {
            throw new \InvalidArgumentException('Coupon code cannot be empty.');
        }
        if ('' === trim($customerId)) {
            throw new \InvalidArgumentException('Customer id cannot be empty.');
        }
        if ('' === trim($orderId)) {
            throw new \InvalidArgumentException('Order id cannot be empty.');
        }

        $coupon = $couponBook->find($couponCode);
        if (null === $coupon) {
            return new PromotionCheckoutReversalResultDTO(
                $ledger,
                null,
                ['checkout_coupon_reversal_coupon_not_found'],
            );
        }

        $reversal = $this->couponService->reverse($coupon, $ledger, $customerId, $orderId);

        return new PromotionCheckoutReversalResultDTO(
            $reversal->ledger,
            $reversal,
            $reversal->redeemed
                ? [...$reversal->reasons, 'checkout_coupon_reversal_completed']
                : [...$reversal->reasons, 'checkout_coupon_reversal_idempotent_noop'],
        );
    }
}
