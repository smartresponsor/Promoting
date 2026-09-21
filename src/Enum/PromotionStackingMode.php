<?php

declare(strict_types=1);

namespace App\Promoting\Enum;

/** Controls whether an applied promotion permits lower-priority promotions to follow it. */
enum PromotionStackingMode: string
{
    case Stackable = 'stackable';
    case Exclusive = 'exclusive';
}
