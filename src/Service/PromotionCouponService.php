<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionCouponRedemptionResultDTO;
use App\Promoting\DTO\PromotionCouponValidationDTO;
use App\Promoting\Enum\PromotionCouponStatus;
use App\Promoting\ServiceInterface\PromotionCouponServiceInterface;
use App\Promoting\ValueObject\PromotionCoupon;
use App\Promoting\ValueObject\PromotionRedemption;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Implements coupon validation, idempotent redemption, and idempotent reversal. */
final class PromotionCouponService implements PromotionCouponServiceInterface
{
    public function validate(
        PromotionCoupon $coupon,
        PromotionRedemptionLedger $ledger,
        string $customerId,
        ?\DateTimeImmutable $at = null,
    ): PromotionCouponValidationDTO {
        $this->assertCustomerId($customerId);

        if (PromotionCouponStatus::Active !== $coupon->status) {
            return new PromotionCouponValidationDTO(false, ['coupon_inactive']);
        }

        $reasons = ['coupon_active'];
        $audience = $this->validateAudience($coupon, $customerId, $reasons);
        if (null !== $audience) {
            return $audience;
        }

        $time = $this->validateTimeWindow($coupon, $at, $reasons);
        if ($time instanceof PromotionCouponValidationDTO) {
            return $time;
        }
        $reasons = $time;

        $limits = $this->validateLimits($coupon, $ledger, $customerId, $reasons);
        if ($limits instanceof PromotionCouponValidationDTO) {
            return $limits;
        }

        return new PromotionCouponValidationDTO(true, [...$limits, 'coupon_valid']);
    }

    private function assertCustomerId(string $customerId): void
    {
        if ('' === trim($customerId)) {
            throw new \InvalidArgumentException('Customer id cannot be empty.');
        }
    }

    /**
     * @param list<string> $reasons
     */
    private function validateAudience(
        PromotionCoupon $coupon,
        string $customerId,
        array &$reasons,
    ): ?PromotionCouponValidationDTO {
        if (null !== $coupon->customerId && $coupon->customerId !== $customerId) {
            return new PromotionCouponValidationDTO(false, [...$reasons, 'coupon_customer_mismatch']);
        }

        $reasons[] = null === $coupon->customerId ? 'coupon_audience_unrestricted' : 'coupon_customer_matched';

        return null;
    }

    /**
     * @param list<string> $reasons
     *
     * @return list<string>|PromotionCouponValidationDTO
     */
    private function validateTimeWindow(
        PromotionCoupon $coupon,
        ?\DateTimeImmutable $at,
        array $reasons,
    ): array|PromotionCouponValidationDTO {
        if (null === $coupon->issuedAt && null === $coupon->startsAt && null === $coupon->endsAt) {
            return $reasons;
        }
        if (null === $at) {
            return new PromotionCouponValidationDTO(false, [...$reasons, 'coupon_time_context_missing']);
        }
        if (null !== $coupon->issuedAt && $at < $coupon->issuedAt) {
            return new PromotionCouponValidationDTO(false, [...$reasons, 'coupon_not_issued_yet']);
        }
        if (null !== $coupon->startsAt && $at < $coupon->startsAt) {
            return new PromotionCouponValidationDTO(false, [...$reasons, 'coupon_not_started']);
        }

        $reasons[] = 'coupon_start_window_met';
        if (null !== $coupon->endsAt && $at > $coupon->endsAt) {
            return new PromotionCouponValidationDTO(false, [...$reasons, 'coupon_ended']);
        }

        return [...$reasons, 'coupon_end_window_met'];
    }

    /**
     * @param list<string> $reasons
     *
     * @return list<string>|PromotionCouponValidationDTO
     */
    private function validateLimits(
        PromotionCoupon $coupon,
        PromotionRedemptionLedger $ledger,
        string $customerId,
        array $reasons,
    ): array|PromotionCouponValidationDTO {
        if (null !== $coupon->usageLimit && $ledger->redeemedCount($coupon->code) >= $coupon->usageLimit) {
            return new PromotionCouponValidationDTO(false, [...$reasons, 'coupon_usage_limit_reached']);
        }
        $reasons[] = 'coupon_usage_limit_available';

        if (
            null !== $coupon->perCustomerLimit
            && $ledger->redeemedCountForCustomer($coupon->code, $customerId) >= $coupon->perCustomerLimit
        ) {
            return new PromotionCouponValidationDTO(false, [...$reasons, 'coupon_customer_limit_reached']);
        }

        return [...$reasons, 'coupon_customer_limit_available'];
    }

    public function redeem(
        PromotionCoupon $coupon,
        PromotionRedemptionLedger $ledger,
        string $customerId,
        string $orderId,
        ?\DateTimeImmutable $at = null,
    ): PromotionCouponRedemptionResultDTO {
        $trimmedOrderId = trim($orderId);
        if ('' === $trimmedOrderId) {
            throw new \InvalidArgumentException('Order id cannot be empty.');
        }

        $existing = $ledger->findActive($coupon->code, $customerId, $orderId);
        if (null !== $existing) {
            return new PromotionCouponRedemptionResultDTO(
                true,
                $ledger,
                $existing,
                ['coupon_redemption_idempotent_replay'],
            );
        }

        $validation = $this->validate($coupon, $ledger, $customerId, $at);
        if (!$validation->valid) {
            return new PromotionCouponRedemptionResultDTO(false, $ledger, null, $validation->reasons);
        }

        $redemption = new PromotionRedemption($coupon->code, $customerId, $orderId);

        return new PromotionCouponRedemptionResultDTO(
            true,
            $ledger->redeem($redemption),
            $redemption,
            [...$validation->reasons, 'coupon_redeemed'],
        );
    }

    public function reverse(
        PromotionCoupon $coupon,
        PromotionRedemptionLedger $ledger,
        string $customerId,
        string $orderId,
    ): PromotionCouponRedemptionResultDTO {
        $existing = $ledger->findActive($coupon->code, $customerId, $orderId);
        if (null === $existing) {
            return new PromotionCouponRedemptionResultDTO(
                false,
                $ledger,
                null,
                ['coupon_reversal_idempotent_noop'],
            );
        }

        return new PromotionCouponRedemptionResultDTO(
            true,
            $ledger->reverse($coupon->code, $customerId, $orderId),
            $existing->reversed(),
            ['coupon_redemption_reversed'],
        );
    }
}
