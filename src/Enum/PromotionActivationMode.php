<?php

declare(strict_types=1);

namespace App\Promoting\Enum;

/** Declares which explicit execution context is allowed to activate a promotion. */
enum PromotionActivationMode: string
{
    case Automatic = 'automatic';
    case Coupon = 'coupon';
    case Campaign = 'campaign';
}
