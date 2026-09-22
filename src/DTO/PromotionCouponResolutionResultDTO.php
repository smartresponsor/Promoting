<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionCoupon;

/** Coupon-to-promotion resolution outcome with complete eligibility reasons. */
final readonly class PromotionCouponResolutionResultDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public bool $eligible,
        public ?PromotionCoupon $coupon,
        public ?Promotion $promotion,
        public array $reasons,
    ) {
    }
}
