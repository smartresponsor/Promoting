<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

use App\Promoting\Enum\PromotionConditionType;

/** Immutable promotion eligibility condition with validated typed expectation. */
final readonly class PromotionCondition
{
    private function __construct(
        public PromotionConditionType $type,
        public int|string $expected,
    ) {
    }

    /** Creates a minimum-subtotal condition expressed in currency minor units. */
    public static function minimumSubtotal(int $amountMinor): self
    {
        if ($amountMinor < 0) {
            throw new \InvalidArgumentException('Minimum subtotal cannot be negative.');
        }

        return new self(PromotionConditionType::MinimumSubtotal, $amountMinor);
    }

    /** Creates an ISO-style three-letter currency condition. */
    public static function currency(string $currencyCode): self
    {
        $currencyCode = strtoupper(trim($currencyCode));
        if (1 !== preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new \InvalidArgumentException('Currency code must contain exactly three ASCII letters.');
        }

        return new self(PromotionConditionType::Currency, $currencyCode);
    }
}
