<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

use App\Promoting\Enum\PromotionActivationMode;
use App\Promoting\Enum\PromotionStackingMode;
use App\Promoting\Enum\PromotionStatus;

/** Immutable promotion definition combining lifecycle, eligibility rule, and incentive action. */
final readonly class Promotion
{
    public function __construct(
        public string $id,
        public string $label,
        public PromotionRule $rule,
        public PromotionAction $action,
        public PromotionStatus $status = PromotionStatus::Active,
        public int $priority = 0,
        public PromotionStackingMode $stackingMode = PromotionStackingMode::Stackable,
        public ?PromotionBenefit $benefit = null,
        public ?\DateTimeImmutable $startsAt = null,
        public ?\DateTimeImmutable $endsAt = null,
        public PromotionActivationMode $activationMode = PromotionActivationMode::Automatic,
    ) {
        if ('' === trim($id)) {
            throw new \InvalidArgumentException('Promotion id cannot be empty.');
        }
        if ('' === trim($label)) {
            throw new \InvalidArgumentException('Promotion label cannot be empty.');
        }
        if (null !== $startsAt && null !== $endsAt && $startsAt > $endsAt) {
            throw new \InvalidArgumentException('Promotion start cannot be after promotion end.');
        }
    }

    /** Returns the same promotion definition with an explicitly selected lifecycle status. */
    public function withStatus(PromotionStatus $status): self
    {
        return new self(
            $this->id,
            $this->label,
            $this->rule,
            $this->action,
            $status,
            $this->priority,
            $this->stackingMode,
            $this->benefit,
            $this->startsAt,
            $this->endsAt,
            $this->activationMode,
        );
    }
}
