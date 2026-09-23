<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignSpend;
use App\Promoting\ValueObject\PromotionCampaignSpendLedger;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Checkout promotion application outcome carrying the read-only plan and resulting promotion-owned ledger state. */
final readonly class PromotionCheckoutApplicationResultDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public PromotionCheckoutPlanResultDTO $plan,
        public PromotionRedemptionLedger $ledger,
        public ?PromotionCouponRedemptionResultDTO $couponRedemption,
        public array $reasons,
        public ?PromotionCampaign $campaign = null,
        public ?PromotionCampaignSpendLedger $campaignSpendLedger = null,
        public ?PromotionCampaignSpend $campaignSpend = null,
    ) {
    }
}
