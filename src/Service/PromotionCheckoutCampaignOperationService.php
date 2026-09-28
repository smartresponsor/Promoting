<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionCheckoutCampaignEffectResultDTO;
use App\Promoting\DTO\PromotionCheckoutCampaignPlanningResultDTO;
use App\Promoting\DTO\PromotionCheckoutPlanResultDTO;
use App\Promoting\ServiceInterface\PromotionCampaignServiceInterface;
use App\Promoting\ServiceInterface\PromotionCampaignUsageServiceInterface;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignSpend;
use App\Promoting\ValueObject\PromotionCampaignSpendLedger;
use App\Promoting\ValueObject\PromotionCampaignUsageLedger;

/** Owns campaign-specific checkout replay, usage, and spend accounting operations. */
final readonly class PromotionCheckoutCampaignOperationService
{
    public function __construct(
        private PromotionCampaignServiceInterface $campaignService,
        private PromotionCampaignUsageServiceInterface $usageService,
    ) {
    }

    public function preparePlanning(
        ?PromotionCampaign $campaign,
        ?PromotionCampaignSpendLedger $spendLedger,
        ?PromotionCampaignUsageLedger $usageLedger,
        ?string $orderId,
    ): PromotionCheckoutCampaignPlanningResultDTO {
        if (null === $campaign) {
            if (null !== $spendLedger || null !== $usageLedger) {
                throw new \InvalidArgumentException('Campaign context is required when a campaign ledger is provided.');
            }

            return new PromotionCheckoutCampaignPlanningResultDTO(null, null, false, false);
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

        return new PromotionCheckoutCampaignPlanningResultDTO(
            $usageRejected ? null : $planningCampaign,
            $existingSpend,
            null !== $existingUsage,
            $usageRejected,
        );
    }

    public function apply(
        ?PromotionCampaign $campaign,
        ?PromotionCampaignSpendLedger $spendLedger,
        ?PromotionCampaignUsageLedger $usageLedger,
        ?PromotionCampaignSpend $existingSpend,
        PromotionCheckoutPlanResultDTO $plan,
        int $discountAmountMinor,
        bool $budgetRejected,
        bool $usageRejected,
        ?string $orderId,
    ): PromotionCheckoutCampaignEffectResultDTO {
        if (null === $campaign) {
            return new PromotionCheckoutCampaignEffectResultDTO(null, $spendLedger, null, $usageLedger, []);
        }

        [$resultUsageLedger, $reasons] = $this->recordUsage(
            $campaign,
            $usageLedger,
            $plan,
            $usageRejected,
            $orderId,
        );

        if ($budgetRejected) {
            return new PromotionCheckoutCampaignEffectResultDTO(
                $campaign,
                $spendLedger,
                null,
                $resultUsageLedger,
                [...$reasons, 'checkout_campaign_spend_not_recorded_budget'],
            );
        }
        if (null === $plan->campaignSelection || !$plan->campaignSelection->available) {
            return new PromotionCheckoutCampaignEffectResultDTO(
                $campaign,
                $spendLedger,
                null,
                $resultUsageLedger,
                [...$reasons, 'checkout_campaign_not_applied'],
            );
        }
        if ($discountAmountMinor < 1) {
            return new PromotionCheckoutCampaignEffectResultDTO(
                $campaign,
                $spendLedger,
                null,
                $resultUsageLedger,
                [...$reasons, 'checkout_campaign_no_monetary_spend'],
            );
        }
        if (null !== $existingSpend) {
            return new PromotionCheckoutCampaignEffectResultDTO(
                $campaign,
                $spendLedger,
                $existingSpend,
                $resultUsageLedger,
                [...$reasons, 'campaign_spend_idempotent_replay'],
            );
        }
        if (null === $spendLedger) {
            throw new \LogicException('Campaign spend ledger must be available for campaign application.');
        }

        $spend = new PromotionCampaignSpend(
            $campaign->id,
            $this->requireOrderId($orderId),
            $discountAmountMinor,
        );

        return new PromotionCheckoutCampaignEffectResultDTO(
            $this->campaignService->recordSpend($campaign, $discountAmountMinor),
            $spendLedger->record($spend),
            $spend,
            $resultUsageLedger,
            [...$reasons, 'checkout_campaign_spend_recorded'],
        );
    }

    /** @return array{?PromotionCampaignUsageLedger, list<string>} */
    private function recordUsage(
        PromotionCampaign $campaign,
        ?PromotionCampaignUsageLedger $usageLedger,
        PromotionCheckoutPlanResultDTO $plan,
        bool $usageRejected,
        ?string $orderId,
    ): array {
        if ($usageRejected) {
            return [$usageLedger, ['checkout_campaign_usage_not_recorded_limit']];
        }
        if (null === $usageLedger || !$this->participated($plan)) {
            return [$usageLedger, []];
        }

        $mutation = $this->usageService->record($campaign, $usageLedger, $this->requireOrderId($orderId));

        return [$mutation->ledger, $mutation->reasons];
    }

    private function participated(PromotionCheckoutPlanResultDTO $plan): bool
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

    private function requireOrderId(?string $orderId): string
    {
        if (null === $orderId || '' === trim($orderId)) {
            throw new \InvalidArgumentException('Order id is required when coupon or campaign context is provided.');
        }

        return $orderId;
    }
}
