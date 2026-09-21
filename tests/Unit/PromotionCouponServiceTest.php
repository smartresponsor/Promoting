<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\Service\PromotionCouponService;
use App\Promoting\ValueObject\PromotionCoupon;
use App\Promoting\ValueObject\PromotionRedemptionLedger;
use PHPUnit\Framework\TestCase;

/** Verifies coupon limits plus idempotent redemption and reversal semantics. */
final class PromotionCouponServiceTest extends TestCase
{
    public function testRedemptionReplayIsIdempotentAndReversalReleasesLimits(): void
    {
        $service = new PromotionCouponService();
        $coupon = new PromotionCoupon('save10', 'promo-1', usageLimit: 1, perCustomerLimit: 1);
        $ledger = new PromotionRedemptionLedger();

        $first = $service->redeem($coupon, $ledger, 'customer-1', 'order-1');
        self::assertTrue($first->redeemed);
        self::assertSame(1, $first->ledger->redeemedCount('SAVE10'));

        $replay = $service->redeem($coupon, $first->ledger, 'customer-1', 'order-1');
        self::assertTrue($replay->redeemed);
        self::assertSame($first->ledger, $replay->ledger);
        self::assertSame(['coupon_redemption_idempotent_replay'], $replay->reasons);

        $blocked = $service->redeem($coupon, $first->ledger, 'customer-2', 'order-2');
        self::assertFalse($blocked->redeemed);
        self::assertSame(['coupon_active', 'coupon_usage_limit_reached'], $blocked->reasons);

        $reversed = $service->reverse($coupon, $first->ledger, 'customer-1', 'order-1');
        self::assertTrue($reversed->redeemed);
        self::assertSame(0, $reversed->ledger->redeemedCount('SAVE10'));

        $reverseReplay = $service->reverse($coupon, $reversed->ledger, 'customer-1', 'order-1');
        self::assertFalse($reverseReplay->redeemed);
        self::assertSame($reversed->ledger, $reverseReplay->ledger);
        self::assertSame(['coupon_reversal_idempotent_noop'], $reverseReplay->reasons);
    }

    public function testPerCustomerLimitIsIndependentFromGlobalCapacity(): void
    {
        $service = new PromotionCouponService();
        $coupon = new PromotionCoupon('team', 'promo-2', usageLimit: 3, perCustomerLimit: 1);
        $ledger = $service->redeem($coupon, new PromotionRedemptionLedger(), 'customer-1', 'order-1')->ledger;

        $sameCustomer = $service->redeem($coupon, $ledger, 'customer-1', 'order-2');
        self::assertFalse($sameCustomer->redeemed);
        self::assertSame(
            ['coupon_active', 'coupon_usage_limit_available', 'coupon_customer_limit_reached'],
            $sameCustomer->reasons,
        );

        $otherCustomer = $service->redeem($coupon, $ledger, 'customer-2', 'order-2');
        self::assertTrue($otherCustomer->redeemed);
    }
}
