<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

use App\Promoting\Enum\PromotionCampaignUsageStatus;

/** Immutable campaign application history with active-count and idempotent reversal semantics. */
final readonly class PromotionCampaignUsageLedger
{
    /** @param list<PromotionCampaignUsage> $entries */
    public function __construct(public array $entries = [])
    {
    }

    public function activeCount(string $campaignId): int
    {
        $count = 0;
        foreach ($this->entries as $entry) {
            if (
                PromotionCampaignUsageStatus::Applied === $entry->status
                && $entry->campaignId === $campaignId
            ) {
                ++$count;
            }
        }

        return $count;
    }

    public function findActive(string $campaignId, string $orderId): ?PromotionCampaignUsage
    {
        foreach ($this->entries as $entry) {
            if (
                PromotionCampaignUsageStatus::Applied === $entry->status
                && $entry->campaignId === $campaignId
                && $entry->orderId === $orderId
            ) {
                return $entry;
            }
        }

        return null;
    }

    public function record(PromotionCampaignUsage $usage): self
    {
        if (null !== $this->findActive($usage->campaignId, $usage->orderId)) {
            return $this;
        }

        return new self([...$this->entries, $usage]);
    }

    public function reverse(string $campaignId, string $orderId): self
    {
        $reversed = false;
        $entries = [];
        foreach ($this->entries as $entry) {
            if (
                !$reversed
                && PromotionCampaignUsageStatus::Applied === $entry->status
                && $entry->campaignId === $campaignId
                && $entry->orderId === $orderId
            ) {
                $entries[] = $entry->reversed();
                $reversed = true;
                continue;
            }

            $entries[] = $entry;
        }

        return $reversed ? new self($entries) : $this;
    }
}
