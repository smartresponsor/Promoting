<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

use App\Promoting\Enum\PromotionCampaignUsageStatus;

/** Immutable record of one campaign application keyed by campaign and order identity. */
final readonly class PromotionCampaignUsage
{
    public function __construct(
        public string $campaignId,
        public string $orderId,
        public PromotionCampaignUsageStatus $status = PromotionCampaignUsageStatus::Applied,
    ) {
        if ('' === trim($campaignId)) {
            throw new \InvalidArgumentException('Campaign id cannot be empty for usage.');
        }
        if ('' === trim($orderId)) {
            throw new \InvalidArgumentException('Order id cannot be empty for campaign usage.');
        }
    }

    public function key(): string
    {
        return $this->campaignId.'|'.$this->orderId;
    }

    public function reversed(): self
    {
        return new self($this->campaignId, $this->orderId, PromotionCampaignUsageStatus::Reversed);
    }
}
