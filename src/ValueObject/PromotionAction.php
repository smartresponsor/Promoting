<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

use App\Promoting\Enum\PromotionApplicationMethod;

/** Immutable commercial incentive action with method-specific validated magnitude. */
final readonly class PromotionAction
{
    private function __construct(
        public PromotionApplicationMethod $method,
        public int $amount,
    ) {
    }

    /** Creates a fixed discount expressed in currency minor units. */
    public static function fixed(int $amountMinor): self
    {
        if ($amountMinor < 0) {
            throw new \InvalidArgumentException('Fixed discount cannot be negative.');
        }

        return new self(PromotionApplicationMethod::Fixed, $amountMinor);
    }

    /** Creates a percentage discount expressed in basis points, from 0 through 10000. */
    public static function percentage(int $basisPoints): self
    {
        if ($basisPoints < 0 || $basisPoints > 10_000) {
            throw new \InvalidArgumentException('Percentage discount must be between 0 and 10000 basis points.');
        }

        return new self(PromotionApplicationMethod::Percentage, $basisPoints);
    }
}
