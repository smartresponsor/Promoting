<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionCampaignUsageMutationResultDTO;
use App\Promoting\DTO\PromotionCampaignUsageValidationDTO;
use App\Promoting\ServiceInterface\PromotionCampaignUsageServiceInterface;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignUsage;
use App\Promoting\ValueObject\PromotionCampaignUsageLedger;

/** Enforces campaign application counts independently from monetary campaign spend. */
final readonly class PromotionCampaignUsageService implements PromotionCampaignUsageServiceInterface
{
    public function validate(
        PromotionCampaign $campaign,
        PromotionCampaignUsageLedger $ledger,
        string $orderId,
    ): PromotionCampaignUsageValidationDTO {
        $this->assertOrderId($orderId);

        if (null !== $ledger->findActive($campaign->id, $orderId)) {
            return new PromotionCampaignUsageValidationDTO(
                true,
                ['campaign_usage_idempotent_replay'],
            );
        }

        if (null === $campaign->applicationLimit) {
            return new PromotionCampaignUsageValidationDTO(
                true,
                ['campaign_usage_unlimited'],
            );
        }

        if ($ledger->activeCount($campaign->id) >= $campaign->applicationLimit) {
            return new PromotionCampaignUsageValidationDTO(
                false,
                ['campaign_application_limit_reached'],
            );
        }

        return new PromotionCampaignUsageValidationDTO(
            true,
            ['campaign_application_limit_available'],
        );
    }

    public function record(
        PromotionCampaign $campaign,
        PromotionCampaignUsageLedger $ledger,
        string $orderId,
    ): PromotionCampaignUsageMutationResultDTO {
        $validation = $this->validate($campaign, $ledger, $orderId);
        $existing = $ledger->findActive($campaign->id, $orderId);
        if (null !== $existing) {
            return new PromotionCampaignUsageMutationResultDTO(
                false,
                $ledger,
                $existing,
                $validation->reasons,
            );
        }

        if (!$validation->allowed) {
            return new PromotionCampaignUsageMutationResultDTO(
                false,
                $ledger,
                null,
                [...$validation->reasons, 'campaign_usage_not_recorded'],
            );
        }

        $usage = new PromotionCampaignUsage($campaign->id, $orderId);

        return new PromotionCampaignUsageMutationResultDTO(
            true,
            $ledger->record($usage),
            $usage,
            [...$validation->reasons, 'campaign_usage_recorded'],
        );
    }

    public function reverse(
        PromotionCampaign $campaign,
        PromotionCampaignUsageLedger $ledger,
        string $orderId,
    ): PromotionCampaignUsageMutationResultDTO {
        $this->assertOrderId($orderId);

        $existing = $ledger->findActive($campaign->id, $orderId);
        if (null === $existing) {
            return new PromotionCampaignUsageMutationResultDTO(
                false,
                $ledger,
                null,
                ['campaign_usage_reversal_idempotent_noop'],
            );
        }

        return new PromotionCampaignUsageMutationResultDTO(
            true,
            $ledger->reverse($campaign->id, $orderId),
            $existing->reversed(),
            ['campaign_usage_reversed'],
        );
    }

    private function assertOrderId(string $orderId): void
    {
        if ('' === trim($orderId)) {
            throw new \InvalidArgumentException('Order id cannot be empty for campaign usage.');
        }
    }
}
