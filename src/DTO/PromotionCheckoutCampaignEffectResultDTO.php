<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignSpend;
use App\Promoting\ValueObject\PromotionCampaignSpendLedger;
use App\Promoting\ValueObject\PromotionCampaignUsageLedger;

/** Campaign state and ledger effects produced after unified checkout resolution. */
final readonly class PromotionCheckoutCampaignEffectResultDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public ?PromotionCampaign $campaign,
        public ?PromotionCampaignSpendLedger $spendLedger,
        public ?PromotionCampaignSpend $spend,
        public ?PromotionCampaignUsageLedger $usageLedger,
        public array $reasons,
    ) {
    }
}
