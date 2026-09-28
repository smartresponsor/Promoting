<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\Promotion;

/** Candidate promotions plus campaign/coupon audit decisions before final stacking resolution. */
final readonly class PromotionCheckoutCandidateResultDTO
{
    /**
     * @param list<Promotion> $promotions
     * @param list<string>    $reasons
     */
    public function __construct(
        public array $promotions,
        public ?PromotionCouponResolutionResultDTO $couponResolution,
        public ?PromotionCampaignSelectionResultDTO $campaignSelection,
        public array $reasons,
    ) {
    }
}
