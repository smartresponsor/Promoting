<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Coupon planning state used to make same-order checkout replays idempotent. */
final readonly class PromotionCheckoutCouponPlanningResultDTO
{
    public function __construct(
        public PromotionRedemptionLedger $ledger,
        public bool $replay,
    ) {
    }
}
