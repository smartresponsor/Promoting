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
        [
            $planningLedger,
            $couponReplay,
            $planningCampaign,
            $existingCampaignSpend,
            $campaignUsageReplay,
            $campaignUsageRejected,
        ] = $this->preparePlanningState(
            $ledger,
            $couponCode,
            $customerId,
            $orderId,
            $campaign,
            $campaignSpendLedger,
            $campaignUsageLedger,
        );

        [$plan, $campaignDiscountAmountMinor, $campaignBudgetRejected] = $this->planCheckout(
            $catalog, $couponBook, $planningLedger, $request, $benefitRequest,
            $couponCode, $customerId, $planningCampaign, $existingCampaignSpend,
            $campaignUsageReplay, $campaignUsageRejected,
        );
        [$resultLedger, $couponRedemption, $couponReasons] = $this->applyCouponResult(
            $ledger, $plan, $couponCode, $customerId, $orderId, $request, $couponReplay,
        );
        [$resultCampaign, $resultCampaignSpendLedger, $campaignSpend, $resultCampaignUsageLedger, $campaignReasons]
            = $this->applyCampaignResult(
                $campaign, $campaignSpendLedger, $campaignUsageLedger, $existingCampaignSpend,
                $plan, $campaignDiscountAmountMinor, $campaignBudgetRejected, $campaignUsageRejected, $orderId,
            );

        return new PromotionCheckoutApplicationResultDTO(
            $plan,
            $resultLedger,
            $couponRedemption,
            [...$couponReasons, ...$campaignReasons],
            $resultCampaign,
            $resultCampaignSpendLedger,
            $campaignSpend,
            $resultCampaignUsageLedger,
        );
    }

    /**
     * @return array{
     *   PromotionRedemptionLedger,
     *   bool,
     *   ?PromotionCampaign,
     *   ?PromotionCampaignSpend,
     *   bool,
     *   bool
     * }
     */
    private function preparePlanningState(
        PromotionRedemptionLedger $ledger,
        ?string $couponCode,
        ?string $customerId,
        ?string $orderId,
        ?PromotionCampaign $campaign,
        ?PromotionCampaignSpendLedger $campaignSpendLedger,
        ?PromotionCampaignUsageLedger $campaignUsageLedger,
    ): array {
        [$planningLedger, $couponReplay] = $this->prepareCouponPlanning(
            $ledger,
            $couponCode,
            $customerId,
            $orderId,
        );
        [$planningCampaign, $existingSpend, $usageReplay, $usageRejected] = $this->prepareCampaignPlanning(
            $campaign,
            $campaignSpendLedger,
            $campaignUsageLedger,
            $orderId,
        );

        return [$planningLedger, $couponReplay, $planningCampaign, $existingSpend, $usageReplay, $usageRejected];
    }

    /** @return array{PromotionRedemptionLedger, bool} */
    private function prepareCouponPlanning(
        PromotionRedemptionLedger $ledger,
        ?string $couponCode,
        ?string $customerId,
        ?string $orderId,
    ): array {
        if (null === $couponCode) {
            return [$ledger, false];
        }
        if (null === $customerId || '' === trim($customerId)) {
            throw new \InvalidArgumentException('Customer id is required when a coupon code is provided.');
        }

        $orderId = $this->requireOrderId($orderId);
        $existing = $ledger->findActive($couponCode, $customerId, $orderId);

        return [
            null === $existing ? $ledger : $ledger->reverse($couponCode, $customerId, $orderId),
            null !== $existing,
        ];
    }

    /** @return array{?PromotionCampaign, ?PromotionCampaignSpend, bool, bool} */
    private function prepareCampaignPlanning(
        ?PromotionCampaign $campaign,
        ?PromotionCampaignSpendLedger $spendLedger,
        ?PromotionCampaignUsageLedger $usageLedger,
        ?string $orderId,
    ): array {
        if (null === $campaign) {
            if (null !== $spendLedger || null !== $usageLedger) {
                throw new \InvalidArgumentException('Campaign context is required when a campaign ledger is provided.');
            }

            return [null, null, false, false];
        }
        if (null === $spendLedger) {
            throw new \InvalidArgumentException('Campaign spend ledger is required when a campaign is provided.');
        }
        if ($campaign->spentMinor !== $spendLedger->activeSpendForCampaign($campaign->id)) {
            throw new \DomainException('Campaign spend aggregate does not match campaign spend ledger.');
        }
        if (null !== $campaign->applicationLimit && null === $usageLedger) {
            throw new \InvalidArgumentException('Campaign usage ledger is required when an application limit is configured.');
        }

        $orderId = $this->requireOrderId($orderId);
        $existingSpend = $spendLedger->findActive($campaign->id, $orderId);
        $existingUsage = $usageLedger?->findActive($campaign->id, $orderId);
        if (null !== $existingSpend && null !== $usageLedger && null === $existingUsage) {
            throw new \DomainException('Campaign spend replay is missing its campaign usage record.');
        }

        $planningCampaign = null === $existingSpend
            ? $campaign
            : $this->campaignService->releaseSpend($campaign, $existingSpend->amountMinor);
        $usageRejected = null !== $usageLedger
            && !$this->usageService->validate($campaign, $usageLedger, $orderId)->allowed;

        return [
            $usageRejected ? null : $planningCampaign,
            $existingSpend,
            null !== $existingUsage,
            $usageRejected,
        ];
    }

    /** @return array{PromotionCheckoutPlanResultDTO, int, bool} */
    private function planCheckout(
        PromotionCatalog $catalog,
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $planningLedger,
        PromotionEvaluationRequestDTO $request,
        PromotionBenefitRequestDTO $benefitRequest,
        ?string $couponCode,
        ?string $customerId,
        ?PromotionCampaign $planningCampaign,
        ?PromotionCampaignSpend $existingCampaignSpend,
        bool $campaignUsageReplay,
        bool $campaignUsageRejected,
    ): array {
        $plan = $this->baseCheckoutPlan(
            $catalog,
            $couponBook,
            $planningLedger,
            $request,
            $benefitRequest,
            $couponCode,
            $customerId,
            $planningCampaign,
            $campaignUsageRejected,
        );
        $amountMinor = $this->campaignDiscountAmount($plan);
        $this->assertCampaignReplaySpendMatches($existingCampaignSpend, $amountMinor);

        if (!$this->campaignBudgetExceeded($planningCampaign, $amountMinor)) {
            $this->assertCampaignUsageReplayMatches($campaignUsageReplay, $plan);

            return [$plan, $amountMinor, false];
        }

        $fallback = $this->planService->plan(
            $catalog, $couponBook, $planningLedger, $request, $benefitRequest, $couponCode, $customerId,
        );
        $plan = new PromotionCheckoutPlanResultDTO(
            $fallback->resolution,
            $fallback->couponResolution,
            $plan->campaignSelection,
            $fallback->benefits,
            [...$fallback->reasons, 'checkout_campaign_budget_would_exceed'],
        );
        $this->assertCampaignUsageReplayMatches($campaignUsageReplay, $plan);

        return [$plan, 0, true];
    }

    private function baseCheckoutPlan(
        PromotionCatalog $catalog,
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $ledger,
        PromotionEvaluationRequestDTO $request,
        PromotionBenefitRequestDTO $benefitRequest,
        ?string $couponCode,
        ?string $customerId,
        ?PromotionCampaign $campaign,
        bool $usageRejected,
    ): PromotionCheckoutPlanResultDTO {
        $plan = $this->planService->plan(
            $catalog, $couponBook, $ledger, $request, $benefitRequest, $couponCode, $customerId, $campaign,
        );
        if (!$usageRejected) {
            return $plan;
        }

        return new PromotionCheckoutPlanResultDTO(
            $plan->resolution,
            $plan->couponResolution,
            $plan->campaignSelection,
            $plan->benefits,
            [...$plan->reasons, 'checkout_campaign_application_limit_reached'],
        );
    }

    private function assertCampaignReplaySpendMatches(
        ?PromotionCampaignSpend $existingSpend,
        int $amountMinor,
    ): void {
        if (null !== $existingSpend && $existingSpend->amountMinor !== $amountMinor) {
            throw new \DomainException('Campaign replay spend does not match the current checkout plan.');
        }
    }

    private function assertCampaignUsageReplayMatches(
        bool $usageReplay,
        PromotionCheckoutPlanResultDTO $plan,
    ): void {
        if ($usageReplay && !$this->campaignParticipated($plan)) {
            throw new \DomainException('Campaign usage replay no longer matches the current checkout plan.');
        }
    }

    private function campaignBudgetExceeded(?PromotionCampaign $campaign, int $amountMinor): bool
    {
        return null !== $campaign
            && null !== $campaign->budgetMinor
            && $campaign->spentMinor + $amountMinor > $campaign->budgetMinor;
    }

    /**
     * @return array{PromotionRedemptionLedger, ?\App\Promoting\DTO\PromotionCouponRedemptionResultDTO, list<string>}
     */
    private function applyCouponResult(
        PromotionRedemptionLedger $ledger,
        PromotionCheckoutPlanResultDTO $plan,
        ?string $couponCode,
        ?string $customerId,
        ?string $orderId,
        PromotionEvaluationRequestDTO $request,
        bool $couponReplay,
    ): array {
        if (null === $couponCode || null === $plan->couponResolution) {
            return [$ledger, null, ['checkout_application_completed_without_coupon']];
        }

        $resolution = $plan->couponResolution;
        if (!$resolution->eligible || null === $resolution->coupon || null === $resolution->promotion) {
            if ($couponReplay) {
                throw new \DomainException('Coupon replay no longer matches the current checkout plan.');
            }

            return [$ledger, null, [...$resolution->reasons, 'checkout_coupon_not_redeemed']];
        }
        if (!$this->promotionApplied($plan, $resolution->promotion->id)) {
            if ($couponReplay) {
                throw new \DomainException('Coupon replay no longer matches the current checkout plan.');
            }

            return [$ledger, null, ['checkout_coupon_not_reached_by_resolution']];
        }

        $redemption = $this->couponService->redeem(
            $resolution->coupon,
            $ledger,
            (string) $customerId,
            $this->requireOrderId($orderId),
            $request->at,
        );

        return [
            $redemption->ledger,
            $redemption,
            [
                ...$redemption->reasons,
                $redemption->redeemed ? 'checkout_coupon_redeemed' : 'checkout_coupon_redemption_failed',
            ],
        ];
    }

    /**
     * @return array{?PromotionCampaign, ?PromotionCampaignSpendLedger, ?PromotionCampaignSpend, ?PromotionCampaignUsageLedger, list<string>}
     */
    private function applyCampaignResult(
        ?PromotionCampaign $campaign,
        ?PromotionCampaignSpendLedger $spendLedger,
        ?PromotionCampaignUsageLedger $usageLedger,
        ?PromotionCampaignSpend $existingSpend,
        PromotionCheckoutPlanResultDTO $plan,
        int $discountAmountMinor,
        bool $budgetRejected,
        bool $usageRejected,
        ?string $orderId,
    ): array {
        if (null === $campaign) {
            return [null, $spendLedger, null, $usageLedger, []];
        }

        [$resultUsageLedger, $reasons] = $this->recordCampaignUsage(
            $campaign,
            $usageLedger,
            $plan,
            $usageRejected,
            $orderId,
        );

        if ($budgetRejected) {
            return [$campaign, $spendLedger, null, $resultUsageLedger, [...$reasons, 'checkout_campaign_spend_not_recorded_budget']];
        }
        if (null === $plan->campaignSelection || !$plan->campaignSelection->available) {
            return [$campaign, $spendLedger, null, $resultUsageLedger, [...$reasons, 'checkout_campaign_not_applied']];
        }
        if ($discountAmountMinor < 1) {
            return [$campaign, $spendLedger, null, $resultUsageLedger, [...$reasons, 'checkout_campaign_no_monetary_spend']];
        }
        if (null !== $existingSpend) {
            return [$campaign, $spendLedger, $existingSpend, $resultUsageLedger, [...$reasons, 'campaign_spend_idempotent_replay']];
        }
        if (null === $spendLedger) {
            throw new \LogicException('Campaign spend ledger must be available for campaign application.');
        }

        $spend = new PromotionCampaignSpend(
            $campaign->id,
            $this->requireOrderId($orderId),
            $discountAmountMinor,
        );

        return [
            $this->campaignService->recordSpend($campaign, $discountAmountMinor),
            $spendLedger->record($spend),
            $spend,
            $resultUsageLedger,
            [...$reasons, 'checkout_campaign_spend_recorded'],
        ];
    }

    /** @return array{?PromotionCampaignUsageLedger, list<string>} */
    private function recordCampaignUsage(
        PromotionCampaign $campaign,
        ?PromotionCampaignUsageLedger $usageLedger,
        PromotionCheckoutPlanResultDTO $plan,
        bool $usageRejected,
        ?string $orderId,
    ): array {
        if ($usageRejected) {
            return [$usageLedger, ['checkout_campaign_usage_not_recorded_limit']];
        }
        if (null === $usageLedger || !$this->campaignParticipated($plan)) {
            return [$usageLedger, []];
        }

        $mutation = $this->usageService->record($campaign, $usageLedger, $this->requireOrderId($orderId));

        return [$mutation->ledger, $mutation->reasons];
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
