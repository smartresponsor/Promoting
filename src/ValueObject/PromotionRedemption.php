<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

use App\Promoting\Enum\PromotionRedemptionStatus;

/** One auditable coupon redemption keyed by coupon, customer, and order. */
final readonly class PromotionRedemption
{
    public function __construct(
        public string $couponCode,
        public string $customerId,
        public string $orderId,
        public PromotionRedemptionStatus $status = PromotionRedemptionStatus::Redeemed,
    ) {
        if ('' === trim($couponCode)) {
            throw new \InvalidArgumentException('Coupon code, customer id, and order id are required for redemption.');
        }
        if ('' === trim($customerId)) {
            throw new \InvalidArgumentException('Coupon code, customer id, and order id are required for redemption.');
        }
        if ('' === trim($orderId)) {
            throw new \InvalidArgumentException('Coupon code, customer id, and order id are required for redemption.');
        }
    }

    /** Stable idempotency key for one coupon redemption on one order. */
    public function key(): string
    {
        return strtoupper($this->couponCode).'|'.$this->customerId.'|'.$this->orderId;
    }

    /** Returns the same ledger event in reversed state. */
    public function reversed(): self
    {
        return new self($this->couponCode, $this->customerId, $this->orderId, PromotionRedemptionStatus::Reversed);
    }
}
