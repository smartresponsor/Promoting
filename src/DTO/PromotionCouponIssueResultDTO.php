<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\PromotionCoupon;
use App\Promoting\ValueObject\PromotionCouponBook;

/** Coupon issuance result carrying both the issued coupon and resulting immutable book. */
final readonly class PromotionCouponIssueResultDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public PromotionCoupon $coupon,
        public PromotionCouponBook $book,
        public array $reasons,
    ) {
    }
}
