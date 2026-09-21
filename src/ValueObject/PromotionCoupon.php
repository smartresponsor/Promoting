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
        public ?\DateTimeImmutable $issuedAt = null,
        public ?\DateTimeImmutable $startsAt = null,
        public ?\DateTimeImmutable $endsAt = null,
        public ?string $customerId = null,
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
        if (null !== $startsAt && null !== $endsAt && $startsAt > $endsAt) {
            throw new \InvalidArgumentException('Coupon start cannot be after coupon end.');
        }
        if (null !== $customerId && '' === trim($customerId)) {
            throw new \InvalidArgumentException('Coupon customer id cannot be empty when provided.');
        }

        $this->code = $normalizedCode;
    }

    /** Returns the same coupon with another lifecycle status. */
    public function withStatus(PromotionCouponStatus $status): self
    {
        return new self(
            $this->code,
            $this->promotionId,
            $this->usageLimit,
            $this->perCustomerLimit,
            $status,
            $this->issuedAt,
            $this->startsAt,
            $this->endsAt,
            $this->customerId,
        );
    }
}
