<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Coupon effect produced after the unified checkout resolution decides participation. */
final readonly class PromotionCheckoutCouponEffectResultDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public PromotionRedemptionLedger $ledger,
        public ?PromotionCouponRedemptionResultDTO $redemption,
        public array $reasons,
    ) {
    }
}
