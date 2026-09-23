<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Checkout coupon reversal outcome carrying the resulting immutable promotion redemption ledger. */
final readonly class PromotionCheckoutReversalResultDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public PromotionRedemptionLedger $ledger,
        public ?PromotionCouponRedemptionResultDTO $couponReversal,
        public array $reasons,
    ) {
    }
}
