<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Service\PromotionCouponService;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionCoupon;
use App\Promoting\ValueObject\PromotionRedemptionLedger;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Covers reachable short-circuit combinations for promotion and coupon validity windows. */
final class PromotionReachablePathTest extends TestCase
{
    public function testEndOnlyPromotionWindowIsEligibleBeforeEnd(): void
    {
        $promotion = new Promotion(
            'end-only',
            'End only',
            new PromotionRule(),
            PromotionAction::fixed(1),
            endsAt: new \DateTimeImmutable('2026-09-30T00:00:00+00:00'),
        );

        $result = (new PromotionEvaluationService())->evaluate(
            $promotion,
            new PromotionEvaluationRequestDTO(
                100,
                'USD',
                new \DateTimeImmutable('2026-09-22T00:00:00+00:00'),
            ),
        );

        self::assertTrue($result->eligible);
        self::assertSame(
            ['promotion_active', 'promotion_start_window_met', 'promotion_end_window_met', 'promotion_eligible'],
            $result->reasons,
        );
    }

    public function testIssuedOnlyCouponIsValidAfterIssuance(): void
    {
        $coupon = new PromotionCoupon(
            'issued',
            'p',
            issuedAt: new \DateTimeImmutable('2026-09-20T00:00:00+00:00'),
        );

        $result = (new PromotionCouponService())->validate(
            $coupon,
            new PromotionRedemptionLedger(),
            'customer',
            new \DateTimeImmutable('2026-09-22T00:00:00+00:00'),
        );

        self::assertTrue($result->valid);
        self::assertContains('coupon_start_window_met', $result->reasons);
        self::assertContains('coupon_end_window_met', $result->reasons);
    }

    public function testStartOnlyCouponIsValidAfterStart(): void
    {
        $coupon = new PromotionCoupon(
            'starts',
            'p',
            startsAt: new \DateTimeImmutable('2026-09-20T00:00:00+00:00'),
        );

        self::assertTrue(
            (new PromotionCouponService())->validate(
                $coupon,
                new PromotionRedemptionLedger(),
                'customer',
                new \DateTimeImmutable('2026-09-22T00:00:00+00:00'),
            )->valid,
        );
    }

    public function testEndOnlyCouponIsValidBeforeEnd(): void
    {
        $coupon = new PromotionCoupon(
            'ends',
            'p',
            endsAt: new \DateTimeImmutable('2026-09-30T00:00:00+00:00'),
        );

        self::assertTrue(
            (new PromotionCouponService())->validate(
                $coupon,
                new PromotionRedemptionLedger(),
                'customer',
                new \DateTimeImmutable('2026-09-22T00:00:00+00:00'),
            )->valid,
        );
    }

    public function testCustomerBoundCouponWithoutTimeMetadataIsValidForOwner(): void
    {
        $coupon = new PromotionCoupon('owner', 'p', customerId: 'customer');

        $result = (new PromotionCouponService())->validate(
            $coupon,
            new PromotionRedemptionLedger(),
            'customer',
        );

        self::assertTrue($result->valid);
        self::assertContains('coupon_customer_matched', $result->reasons);
    }
}
