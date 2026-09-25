<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

use App\Promoting\Enum\PromotionCampaignStatus;

/** Immutable campaign grouping promotions under lifecycle, window, and spend controls. */
final readonly class PromotionCampaign
{
    /**
     * @param list<string> $promotionIds
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $promotionIds,
        public ?\DateTimeImmutable $startsAt = null,
        public ?\DateTimeImmutable $endsAt = null,
        public ?int $budgetMinor = null,
        public int $spentMinor = 0,
        public PromotionCampaignStatus $status = PromotionCampaignStatus::Inactive,
        public ?int $applicationLimit = null,
    ) {
        if ('' === trim($id) || '' === trim($label)) {
            throw new \InvalidArgumentException('Campaign id and label cannot be empty.');
        }
        if ([] === $promotionIds) {
            throw new \InvalidArgumentException('Campaign must group at least one promotion.');
        }
        foreach ($promotionIds as $promotionId) {
            if ('' === trim($promotionId)) {
                throw new \InvalidArgumentException('Campaign promotion ids cannot be empty.');
            }
        }
        if (null !== $startsAt && null !== $endsAt && $startsAt > $endsAt) {
            throw new \InvalidArgumentException('Campaign start cannot be after campaign end.');
        }
        if (null !== $budgetMinor && $budgetMinor < 1) {
            throw new \InvalidArgumentException('Campaign budget must be at least one minor unit.');
        }
        if ($spentMinor < 0 || (null !== $budgetMinor && $spentMinor > $budgetMinor)) {
            throw new \InvalidArgumentException('Campaign spend must stay within budget.');
        }
        if (null !== $applicationLimit && $applicationLimit < 1) {
            throw new \InvalidArgumentException('Campaign application limit must be at least one.');
        }
    }

    /** Returns the campaign with another lifecycle status. */
    public function withStatus(PromotionCampaignStatus $status): self
    {
        return new self(
            $this->id,
            $this->label,
            $this->promotionIds,
            $this->startsAt,
            $this->endsAt,
            $this->budgetMinor,
            $this->spentMinor,
            $status,
            $this->applicationLimit,
        );
    }

    /** Returns the campaign with an updated cumulative spend. */
    public function withSpentMinor(int $spentMinor): self
    {
        return new self(
            $this->id,
            $this->label,
            $this->promotionIds,
            $this->startsAt,
            $this->endsAt,
            $this->budgetMinor,
            $spentMinor,
            $this->status,
            $this->applicationLimit,
        );
    }
}
