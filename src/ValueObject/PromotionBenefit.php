<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

use App\Promoting\Enum\PromotionBenefitType;

/** Immutable non-price promotion benefit definition. */
final readonly class PromotionBenefit
{
    private function __construct(
        public PromotionBenefitType $type,
        public ?string $qualifyingSku = null,
        public ?string $rewardSku = null,
        public int $requiredQuantity = 0,
        public int $rewardQuantity = 0,
    ) {
    }

    /** Grants reward units for each complete qualifying quantity block. */
    public static function buyXGetY(
        string $qualifyingSku,
        int $requiredQuantity,
        string $rewardSku,
        int $rewardQuantity,
    ): self {
        $qualifyingSku = trim($qualifyingSku);
        $rewardSku = trim($rewardSku);
        if ('' === $qualifyingSku || '' === $rewardSku) {
            throw new \InvalidArgumentException('BXGY qualifying and reward SKU cannot be empty.');
        }
        if ($requiredQuantity < 1 || $rewardQuantity < 1) {
            throw new \InvalidArgumentException('BXGY quantities must be at least one.');
        }

        return new self(
            PromotionBenefitType::BuyXGetY,
            $qualifyingSku,
            $rewardSku,
            $requiredQuantity,
            $rewardQuantity,
        );
    }

    /** Grants a fixed quantity of a gift SKU when the promotion is otherwise eligible. */
    public static function freeGift(string $giftSku, int $quantity = 1): self
    {
        $giftSku = trim($giftSku);
        if ('' === $giftSku) {
            throw new \InvalidArgumentException('Free gift SKU cannot be empty.');
        }
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Free gift quantity must be at least one.');
        }

        return new self(PromotionBenefitType::FreeGift, rewardSku: $giftSku, rewardQuantity: $quantity);
    }

    /** Grants free-shipping eligibility while leaving shipment execution to Shipping. */
    public static function freeShipping(): self
    {
        return new self(PromotionBenefitType::FreeShipping);
    }
}
