<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionCheckoutPlanResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Builds a read-only checkout promotion plan without mutating cart, order, campaign, or redemption state. */
interface PromotionCheckoutPlanServiceInterface
{
    /** Combines automatic and optional coupon promotions under one deterministic stacking resolution. */
    public function plan(
        PromotionCatalog $catalog,
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $ledger,
        PromotionEvaluationRequestDTO $request,
        PromotionBenefitRequestDTO $benefitRequest,
        ?string $couponCode = null,
        ?string $customerId = null,
        ?PromotionCampaign $campaign = null,
    ): PromotionCheckoutPlanResultDTO;
}
