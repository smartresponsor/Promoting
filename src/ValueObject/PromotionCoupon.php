<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

use App\Promoting\Enum\PromotionCouponStatus;

/** Immutable coupon definition linked to one promotion and guarded by usage limits. */
final readonly class PromotionCoupon
{
    public string $code;

    public function __construct(
        string $code,
        public string $promotionId,
        public ?int $usageLimit = null,
        public ?int $perCustomerLimit = null,
        public PromotionCouponStatus $status = PromotionCouponStatus::Active,
    ) {
        $normalizedCode = strtoupper(trim($code));
        if ('' === $normalizedCode) {
            throw new \InvalidArgumentException('Coupon code cannot be empty.');
        }
        if ('' === trim($promotionId)) {
            throw new \InvalidArgumentException('Coupon promotion id cannot be empty.');
        }
        if (null !== $usageLimit && $usageLimit < 1) {
            throw new \InvalidArgumentException('Coupon usage limit must be at least one.');
        }
        if (null !== $perCustomerLimit && $perCustomerLimit < 1) {
            throw new \InvalidArgumentException('Coupon per-customer limit must be at least one.');
        }

        $this->code = $normalizedCode;
    }
}
