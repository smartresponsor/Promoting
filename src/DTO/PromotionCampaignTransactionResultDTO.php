<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignSpend;
use App\Promoting\ValueObject\PromotionCampaignSpendLedger;

/** Campaign transaction outcome carrying synchronized aggregate and spend-ledger state. */
final readonly class PromotionCampaignTransactionResultDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public bool $successful,
        public PromotionCampaign $campaign,
        public PromotionCampaignSpendLedger $ledger,
        public ?PromotionCampaignApplicationResultDTO $application,
        public ?PromotionCampaignSpend $spend,
        public array $reasons,
    ) {
    }
}
