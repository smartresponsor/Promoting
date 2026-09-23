<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

use App\Promoting\Enum\PromotionCampaignSpendStatus;

/** Immutable campaign spend record tied to one order identity. */
final readonly class PromotionCampaignSpend
{
    public function __construct(
        public string $campaignId,
        public string $orderId,
        public int $amountMinor,
        public PromotionCampaignSpendStatus $status = PromotionCampaignSpendStatus::Spent,
    ) {
        if ('' === trim($campaignId)) {
            throw new \InvalidArgumentException('Campaign id cannot be empty for spend.');
        }
        if ('' === trim($orderId)) {
            throw new \InvalidArgumentException('Order id cannot be empty for campaign spend.');
        }
        if ($amountMinor < 1) {
            throw new \InvalidArgumentException('Campaign spend amount must be at least one minor unit.');
        }
    }

    public function key(): string
    {
        return $this->campaignId.'|'.$this->orderId;
    }

    public function reversed(): self
    {
        return new self(
            $this->campaignId,
            $this->orderId,
            $this->amountMinor,
            PromotionCampaignSpendStatus::Reversed,
        );
    }
}
