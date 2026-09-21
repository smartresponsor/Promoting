<?php

declare(strict_types=1);

namespace App\Promoting\Enum;

/** Represents whether a promotion can participate in evaluation. */
enum PromotionStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
