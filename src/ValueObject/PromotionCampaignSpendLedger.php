<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

use App\Promoting\Enum\PromotionCampaignSpendStatus;

/** Immutable campaign spend history supporting idempotent record and reversal operations. */
final readonly class PromotionCampaignSpendLedger
{
    /** @param list<PromotionCampaignSpend> $entries */
    public function __construct(public array $entries = [])
    {
    }

    public function findActive(string $campaignId, string $orderId): ?PromotionCampaignSpend
    {
        foreach ($this->entries as $entry) {
            if (
                PromotionCampaignSpendStatus::Spent === $entry->status
                && $entry->campaignId === $campaignId
                && $entry->orderId === $orderId
            ) {
                return $entry;
            }
        }

        return null;
    }

    public function activeSpendForCampaign(string $campaignId): int
    {
        $total = 0;
        foreach ($this->entries as $entry) {
            if (
                PromotionCampaignSpendStatus::Spent === $entry->status
                && $entry->campaignId === $campaignId
            ) {
                $total += $entry->amountMinor;
            }
        }

        return $total;
    }

    public function record(PromotionCampaignSpend $spend): self
    {
        if (null !== $this->findActive($spend->campaignId, $spend->orderId)) {
            return $this;
        }

        return new self([...$this->entries, $spend]);
    }

    public function reverse(string $campaignId, string $orderId): self
    {
        $reversed = false;
        $entries = [];
        foreach ($this->entries as $entry) {
            if (
                !$reversed
                && PromotionCampaignSpendStatus::Spent === $entry->status
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
