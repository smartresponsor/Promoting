<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionCouponIssueResultDTO;
use App\Promoting\Enum\PromotionCouponStatus;
use App\Promoting\ServiceInterface\PromotionCouponIssuanceServiceInterface;
use App\Promoting\ValueObject\PromotionCoupon;
use App\Promoting\ValueObject\PromotionCouponBook;

/** Implements unique coupon issuance and idempotent deactivation without hidden mutable state. */
final class PromotionCouponIssuanceService implements PromotionCouponIssuanceServiceInterface
{
    public function issue(
        PromotionCouponBook $book,
        string $code,
        string $promotionId,
        \DateTimeImmutable $issuedAt,
        ?\DateTimeImmutable $startsAt = null,
        ?\DateTimeImmutable $endsAt = null,
        ?string $customerId = null,
        ?int $usageLimit = null,
        ?int $perCustomerLimit = null,
    ): PromotionCouponIssueResultDTO {
        if (null !== $endsAt && $endsAt < $issuedAt) {
            throw new \DomainException('Coupon cannot be issued after its expiration time.');
        }

        $coupon = new PromotionCoupon(
            $code,
            $promotionId,
            $usageLimit,
            $perCustomerLimit,
            PromotionCouponStatus::Active,
            $issuedAt,
            $startsAt,
            $endsAt,
            $customerId,
        );

        return new PromotionCouponIssueResultDTO(
            $coupon,
            $book->issue($coupon),
            ['coupon_issued'],
        );
    }

    public function deactivate(
        PromotionCouponBook $book,
        string $code,
    ): PromotionCouponIssueResultDTO {
        $coupon = $book->find($code);
        if (null === $coupon) {
            throw new \DomainException(sprintf('Coupon code "%s" is not issued.', strtoupper(trim($code))));
        }

        if (PromotionCouponStatus::Inactive === $coupon->status) {
            return new PromotionCouponIssueResultDTO(
                $coupon,
                $book,
                ['coupon_deactivation_idempotent_replay'],
            );
        }

        $inactive = $coupon->withStatus(PromotionCouponStatus::Inactive);

        return new PromotionCouponIssueResultDTO(
            $inactive,
            $book->replace($inactive),
            ['coupon_deactivated'],
        );
    }
}
