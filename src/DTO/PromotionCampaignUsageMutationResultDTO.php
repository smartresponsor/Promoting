<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\PromotionCampaignUsage;
use App\Promoting\ValueObject\PromotionCampaignUsageLedger;

/** Immutable result of recording or reversing one campaign usage entry. */
final readonly class PromotionCampaignUsageMutationResultDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public bool $changed,
        public PromotionCampaignUsageLedger $ledger,
        public ?PromotionCampaignUsage $usage,
        public array $reasons,
    ) {
    }
}
