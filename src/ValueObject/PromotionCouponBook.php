<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

/** Immutable issued-coupon collection enforcing normalized code uniqueness. */
final readonly class PromotionCouponBook
{
    /** @param list<PromotionCoupon> $coupons */
    public function __construct(public array $coupons = [])
    {
        $seen = [];
        foreach ($coupons as $coupon) {
            if (isset($seen[$coupon->code])) {
                throw new \InvalidArgumentException(sprintf('Coupon code "%s" is duplicated.', $coupon->code));
            }
            $seen[$coupon->code] = true;
        }
    }

    /** Returns a coupon by normalized code when it exists. */
    public function find(string $code): ?PromotionCoupon
    {
        $normalizedCode = strtoupper(trim($code));
        foreach ($this->coupons as $coupon) {
            if ($normalizedCode === $coupon->code) {
                return $coupon;
            }
        }

        return null;
    }

    /** Adds a coupon once and rejects a duplicate normalized code. */
    public function issue(PromotionCoupon $coupon): self
    {
        if (null !== $this->find($coupon->code)) {
            throw new \DomainException(sprintf('Coupon code "%s" is already issued.', $coupon->code));
        }

        return new self([...$this->coupons, $coupon]);
    }

    /** Replaces an existing coupon with the same normalized code. */
    public function replace(PromotionCoupon $coupon): self
    {
        $replaced = false;
        $coupons = [];
        foreach ($this->coupons as $existing) {
            if ($existing->code === $coupon->code) {
                $coupons[] = $coupon;
                $replaced = true;
                continue;
            }
            $coupons[] = $existing;
        }

        if (!$replaced) {
            throw new \DomainException(sprintf('Coupon code "%s" is not issued.', $coupon->code));
        }

        return new self($coupons);
    }
}
