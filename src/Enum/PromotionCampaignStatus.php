<?php

declare(strict_types=1);

namespace App\Promoting\Enum;

/** Explicit campaign lifecycle state. */
enum PromotionCampaignStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Paused = 'paused';
}
