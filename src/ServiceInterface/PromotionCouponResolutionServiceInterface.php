<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionCouponResolutionResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Resolves a coupon code to an eligible promotion against explicit catalog and ledger state. */
interface PromotionCouponResolutionServiceInterface
{
    /** Validates coupon and linked promotion without mutating redemption state. */
    public function resolve(
        PromotionCouponBook $couponBook,
        PromotionCatalog $promotionCatalog,
        PromotionRedemptionLedger $ledger,
        string $couponCode,
        string $customerId,
        PromotionEvaluationRequestDTO $request,
    ): PromotionCouponResolutionResultDTO;
}
