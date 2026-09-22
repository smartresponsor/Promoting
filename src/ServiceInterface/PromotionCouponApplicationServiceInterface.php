<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionCouponApplicationResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Applies an issued coupon to its linked promotion and records redemption only after successful application. */
interface PromotionCouponApplicationServiceInterface
{
    public function apply(
        PromotionCouponBook $couponBook,
        PromotionCatalog $promotionCatalog,
        PromotionRedemptionLedger $ledger,
        string $couponCode,
        string $customerId,
        string $orderId,
        PromotionEvaluationRequestDTO $request,
    ): PromotionCouponApplicationResultDTO;
}
