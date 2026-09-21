<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionCouponIssueResultDTO;
use App\Promoting\ValueObject\PromotionCouponBook;

/** Issues and deactivates coupons against an explicit immutable coupon book. */
interface PromotionCouponIssuanceServiceInterface
{
    /** Issues a unique coupon code with explicit lifecycle metadata. */
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
    ): PromotionCouponIssueResultDTO;

    /** Deactivates an issued coupon idempotently. */
    public function deactivate(
        PromotionCouponBook $book,
        string $code,
    ): PromotionCouponIssueResultDTO;
}
