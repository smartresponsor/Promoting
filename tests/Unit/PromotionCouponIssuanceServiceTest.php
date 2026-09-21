<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\Enum\PromotionCouponStatus;
use App\Promoting\Service\PromotionCouponIssuanceService;
use App\Promoting\Service\PromotionCouponService;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;
use PHPUnit\Framework\TestCase;

/** Verifies coupon issuance lifecycle, uniqueness, audience binding, and validity windows. */
final class PromotionCouponIssuanceServiceTest extends TestCase
{
    public function testIssueNormalizesCodeAndRejectsDuplicate(): void
    {
        $service = new PromotionCouponIssuanceService();
        $issuedAt = new \DateTimeImmutable('2026-09-21T12:00:00+00:00');

        $result = $service->issue(
            new PromotionCouponBook(),
            ' save20 ',
            'promo-20',
            $issuedAt,
            usageLimit: 10,
        );

        self::assertSame('SAVE20', $result->coupon->code);
        self::assertSame($issuedAt, $result->coupon->issuedAt);
        self::assertSame(['coupon_issued'], $result->reasons);
        self::assertSame($result->coupon, $result->book->find('save20'));

        $this->expectException(\DomainException::class);
        $service->issue($result->book, 'SAVE20', 'promo-other', $issuedAt);
    }

    public function testDeactivateIsIdempotent(): void
    {
        $service = new PromotionCouponIssuanceService();
        $issued = $service->issue(
            new PromotionCouponBook(),
            'once',
            'promo-once',
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );

        $deactivated = $service->deactivate($issued->book, 'ONCE');
        self::assertSame(PromotionCouponStatus::Inactive, $deactivated->coupon->status);
        self::assertSame(['coupon_deactivated'], $deactivated->reasons);

        $replay = $service->deactivate($deactivated->book, 'once');
        self::assertSame($deactivated->book, $replay->book);
        self::assertSame(['coupon_deactivation_idempotent_replay'], $replay->reasons);
    }

    public function testCustomerBoundWindowedCouponRequiresMatchingAudienceAndTime(): void
    {
        $issuance = new PromotionCouponIssuanceService();
        $couponService = new PromotionCouponService();

        $issued = $issuance->issue(
            new PromotionCouponBook(),
            'vip',
            'promo-vip',
            new \DateTimeImmutable('2026-09-20T00:00:00+00:00'),
            startsAt: new \DateTimeImmutable('2026-09-21T00:00:00+00:00'),
            endsAt: new \DateTimeImmutable('2026-09-30T23:59:59+00:00'),
            customerId: 'customer-vip',
            usageLimit: 2,
            perCustomerLimit: 2,
        );

        $ledger = new PromotionRedemptionLedger();

        $wrongCustomer = $couponService->validate(
            $issued->coupon,
            $ledger,
            'customer-other',
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );
        self::assertFalse($wrongCustomer->valid);
        self::assertSame(['coupon_active', 'coupon_customer_mismatch'], $wrongCustomer->reasons);

        $missingTime = $couponService->validate($issued->coupon, $ledger, 'customer-vip');
        self::assertFalse($missingTime->valid);
        self::assertSame(
            ['coupon_active', 'coupon_customer_matched', 'coupon_time_context_missing'],
            $missingTime->reasons,
        );

        $valid = $couponService->validate(
            $issued->coupon,
            $ledger,
            'customer-vip',
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );
        self::assertTrue($valid->valid);
        self::assertSame(
            [
                'coupon_active',
                'coupon_customer_matched',
                'coupon_start_window_met',
                'coupon_end_window_met',
                'coupon_usage_limit_available',
                'coupon_customer_limit_available',
                'coupon_valid',
            ],
            $valid->reasons,
        );
    }
}
