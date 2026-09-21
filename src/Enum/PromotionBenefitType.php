<?php

declare(strict_types=1);

namespace App\Promoting\Enum;

/** Non-price promotion effects produced for downstream consumers. */
enum PromotionBenefitType: string
{
    case BuyXGetY = 'buy_x_get_y';
    case FreeGift = 'free_gift';
    case FreeShipping = 'free_shipping';
}
