<?php

declare(strict_types=1);

namespace App\Promoting\Enum;

/** Lifecycle of one campaign application usage record. */
enum PromotionCampaignUsageStatus: string
{
    case Applied = 'applied';
    case Reversed = 'reversed';
}
