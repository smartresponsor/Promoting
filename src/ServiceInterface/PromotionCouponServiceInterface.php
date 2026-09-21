<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionCouponRedemptionResultDTO;
use App\Promoting\DTO\PromotionCouponValidationDTO;
use App\Promoting\ValueObject\PromotionCoupon;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Validates and records coupon usage without owning durable persistence. */
interface PromotionCouponServiceInterface
{
    /** Validates lifecycle, global limit, and per-customer limit. */
    public function validate(
        PromotionCoupon $coupon,
        PromotionRedemptionLedger $ledger,
        string $customerId,
    ): PromotionCouponValidationDTO;

    /** Redeems once for a coupon/customer/order key and returns the updated ledger. */
    public function redeem(
        PromotionCoupon $coupon,
        PromotionRedemptionLedger $ledger,
        string $customerId,
        string $orderId,
    ): PromotionCouponRedemptionResultDTO;

    /** Reverses one active redemption idempotently. */
    public function reverse(
        PromotionCoupon $coupon,
        PromotionRedemptionLedger $ledger,
        string $customerId,
        string $orderId,
    ): PromotionCouponRedemptionResultDTO;
}
