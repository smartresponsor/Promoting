<?php

declare(strict_types=1);

namespace App\Promoting\Enum;

/** Identifies how an eligible promotion changes the caller-supplied amount. */
enum PromotionApplicationMethod: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
}
