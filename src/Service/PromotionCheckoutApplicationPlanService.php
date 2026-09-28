<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionCheckoutApplicationPlanResultDTO;
use App\Promoting\DTO\PromotionCheckoutCampaignPlanningResultDTO;
use App\Promoting\DTO\PromotionCheckoutPlanResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionCheckoutPlanServiceInterface;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Finalizes checkout planning against campaign replay, usage, and budget constraints. */
final readonly class PromotionCheckoutApplicationPlanService
{
    public function __construct(private PromotionCheckoutPlanServiceInterface $planService)
    {
    }

    public function plan(
        PromotionCatalog $catalog,
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $ledger,
        PromotionEvaluationRequestDTO $request,
        PromotionBenefitRequestDTO $benefitRequest,
        ?string $couponCode,
        ?string $customerId,
        PromotionCheckoutCampaignPlanningResultDTO $campaignPlanning,
    ): PromotionCheckoutApplicationPlanResultDTO {
        $plan = $this->planService->plan(
            $catalog,
            $couponBook,
            $ledger,
            $request,
            $benefitRequest,
            $couponCode,
            $customerId,
            $campaignPlanning->campaign,
        );
        if ($campaignPlanning->usageRejected) {
            $plan = new PromotionCheckoutPlanResultDTO(
                $plan->resolution,
                $plan->couponResolution,
                $plan->campaignSelection,
                $plan->benefits,
                [...$plan->reasons, 'checkout_campaign_application_limit_reached'],
            );
        }

        $amountMinor = $this->campaignDiscountAmount($plan);
        if (
            null !== $campaignPlanning->existingSpend
            && $campaignPlanning->existingSpend->amountMinor !== $amountMinor
        ) {
            throw new \DomainException('Campaign replay spend does not match the current checkout plan.');
        }

        if (!$this->budgetExceeded($campaignPlanning->campaign, $amountMinor)) {
            $this->assertUsageReplayMatches($campaignPlanning->usageReplay, $plan);

            return new PromotionCheckoutApplicationPlanResultDTO($plan, $amountMinor, false);
        }

        $fallback = $this->planService->plan(
            $catalog,
            $couponBook,
            $ledger,
            $request,
            $benefitRequest,
            $couponCode,
            $customerId,
        );
        $plan = new PromotionCheckoutPlanResultDTO(
            $fallback->resolution,
            $fallback->couponResolution,
            $plan->campaignSelection,
            $fallback->benefits,
            [...$fallback->reasons, 'checkout_campaign_budget_would_exceed'],
        );
        $this->assertUsageReplayMatches($campaignPlanning->usageReplay, $plan);

        return new PromotionCheckoutApplicationPlanResultDTO($plan, 0, true);
    }

    private function budgetExceeded(?PromotionCampaign $campaign, int $amountMinor): bool
    {
        return null !== $campaign
            && null !== $campaign->budgetMinor
            && $campaign->spentMinor + $amountMinor > $campaign->budgetMinor;
    }

    private function assertUsageReplayMatches(bool $usageReplay, PromotionCheckoutPlanResultDTO $plan): void
    {
        if ($usageReplay && !$this->campaignParticipated($plan)) {
            throw new \DomainException('Campaign usage replay no longer matches the current checkout plan.');
        }
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
}
