<?php

declare(strict_types=1);

namespace App\Promoting\Enum;

/** Lifecycle of one campaign spend record. */
enum PromotionCampaignSpendStatus: string
{
    case Spent = 'spent';
    case Reversed = 'reversed';
}
