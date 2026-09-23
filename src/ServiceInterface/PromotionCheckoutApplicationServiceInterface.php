<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionCheckoutApplicationResultDTO;
use App\Promoting\DTO\PromotionCheckoutReversalResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignSpendLedger;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Applies a checkout promotion plan and mutates only promotion-owned redemption state through immutable return values. */
interface PromotionCheckoutApplicationServiceInterface
{
    public function apply(
        PromotionCatalog $catalog,
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $ledger,
        PromotionEvaluationRequestDTO $request,
        PromotionBenefitRequestDTO $benefitRequest,
        ?string $couponCode = null,
        ?string $customerId = null,
        ?string $orderId = null,
        ?PromotionCampaign $campaign = null,
        ?PromotionCampaignSpendLedger $campaignSpendLedger = null,
    ): PromotionCheckoutApplicationResultDTO;

    /** Reverses one checkout coupon redemption idempotently without mutating Cart or Order state. */
    public function reverseCoupon(
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $ledger,
        string $couponCode,
        string $customerId,
        string $orderId,
    ): PromotionCheckoutReversalResultDTO;
}
