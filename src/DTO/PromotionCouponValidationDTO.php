<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

/** Deterministic coupon validation result with stable reason codes. */
final readonly class PromotionCouponValidationDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public bool $valid,
        public array $reasons,
    ) {
    }
}
