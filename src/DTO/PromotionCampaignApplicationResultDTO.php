<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\PromotionCampaign;

/** Campaign application outcome carrying the resulting immutable campaign spend state. */
final readonly class PromotionCampaignApplicationResultDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public bool $applied,
        public PromotionCampaign $campaign,
        public ?PromotionResolutionResultDTO $resolution,
        public array $reasons,
    ) {
    }
}
