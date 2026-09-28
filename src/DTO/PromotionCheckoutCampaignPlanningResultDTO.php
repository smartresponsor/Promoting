<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignSpend;

/** Campaign planning state after replay and usage-limit normalization. */
final readonly class PromotionCheckoutCampaignPlanningResultDTO
{
    public function __construct(
        public ?PromotionCampaign $campaign,
        public ?PromotionCampaignSpend $existingSpend,
        public bool $usageReplay,
        public bool $usageRejected,
    ) {
    }
}
