<?php

declare(strict_types=1);

namespace App\Promoting\Enum;

/** Declares whether a promotion is automatic or requires explicit coupon activation. */
enum PromotionActivationMode: string
{
    case Automatic = 'automatic';
    case Coupon = 'coupon';
}
