<?php

declare(strict_types=1);

namespace App\Promoting\Enum;

/** Identifies the deterministic condition vocabulary supported by promotion rules. */
enum PromotionConditionType: string
{
    case MinimumSubtotal = 'minimum_subtotal';
    case Currency = 'currency';
}
