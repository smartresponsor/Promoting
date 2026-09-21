<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\Enum\PromotionBenefitType;

/** Typed non-price promotion effect for downstream consumers. */
final readonly class PromotionBenefitResultDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public string $promotionId,
        public bool $eligible,
        public ?PromotionBenefitType $type,
        public ?string $rewardSku,
        public int $rewardQuantity,
        public bool $freeShipping,
        public array $reasons,
    ) {
        if ($rewardQuantity < 0) {
            throw new \InvalidArgumentException('Reward quantity cannot be negative.');
        }
        if ($rewardQuantity > 0 && null === $rewardSku) {
            throw new \InvalidArgumentException('Reward SKU is required for positive reward quantity.');
        }
    }
}
