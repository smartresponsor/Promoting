<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** End-to-end coupon application outcome with resulting immutable redemption ledger. */
final readonly class PromotionCouponApplicationResultDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public bool $applied,
        public PromotionRedemptionLedger $ledger,
        public ?PromotionApplicationResultDTO $application,
        public ?PromotionCouponRedemptionResultDTO $redemption,
        public array $reasons,
    ) {
    }
}
