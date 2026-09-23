<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

/** Read-only checkout promotion plan combining monetary resolution, coupon decision, and typed benefits. */
final readonly class PromotionCheckoutPlanResultDTO
{
    /**
     * @param list<PromotionBenefitResultDTO> $benefits
     * @param list<string>                    $reasons
     */
    public function __construct(
        public PromotionResolutionResultDTO $resolution,
        public ?PromotionCouponResolutionResultDTO $couponResolution,
        public ?PromotionCampaignSelectionResultDTO $campaignSelection,
        public array $benefits,
        public array $reasons,
    ) {
    }
}
