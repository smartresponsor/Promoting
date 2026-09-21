<?php

declare(strict_types=1);

namespace App\Promoting\Enum;

/** Coupon lifecycle state independent from promotion lifecycle. */
enum PromotionCouponStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
