<?php

declare(strict_types=1);

namespace App\Promoting\Enum;

/** Lifecycle of one coupon redemption ledger entry. */
enum PromotionRedemptionStatus: string
{
    case Redeemed = 'redeemed';
    case Reversed = 'reversed';
}
