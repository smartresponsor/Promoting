<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\PromotionRedemption;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Coupon redemption outcome carrying the resulting immutable ledger. */
final readonly class PromotionCouponRedemptionResultDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public bool $redeemed,
        public PromotionRedemptionLedger $ledger,
        public ?PromotionRedemption $redemption,
        public array $reasons,
    ) {
    }
}
