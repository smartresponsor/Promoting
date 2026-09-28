<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

/** Final checkout plan plus campaign accounting decisions needed by application. */
final readonly class PromotionCheckoutApplicationPlanResultDTO
{
    public function __construct(
        public PromotionCheckoutPlanResultDTO $plan,
        public int $campaignDiscountAmountMinor,
        public bool $campaignBudgetRejected,
    ) {
    }
}
