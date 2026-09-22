<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\Enum\PromotionCampaignStatus;
use App\Promoting\Enum\PromotionCouponStatus;
use App\Promoting\Enum\PromotionRedemptionStatus;
use App\Promoting\Service\PromotionCampaignService;
use App\Promoting\Service\PromotionCouponIssuanceService;
use App\Promoting\Service\PromotionCouponService;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCoupon;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemption;
use App\Promoting\ValueObject\PromotionRedemptionLedger;
use PHPUnit\Framework\TestCase;

/** Exercises lifecycle, time-window, idempotency, and error paths in stateful promotion services. */
final class PromotionServiceEdgeCaseTest extends TestCase
{
    public function testCampaignLifecycleNoOpsAndUnboundedSpend(): void
    {
        $service = new PromotionCampaignService();
        $inactive = new PromotionCampaign('c', 'Campaign', ['p']);

        self::assertSame($inactive, $service->pause($inactive));
        self::assertSame(
            $inactive,
            $service->resume($inactive, new \DateTimeImmutable('2026-09-22T12:00:00+00:00')),
        );

        $active = $inactive->withStatus(PromotionCampaignStatus::Active);
        $spent = $service->recordSpend($active, 25);
        self::assertSame(25, $spent->spentMinor);
        self::assertTrue($service->evaluate($spent, new \DateTimeImmutable('2026-09-22T12:00:00+00:00'))->available);
    }

    public function testCampaignEndedAndNegativeSpendPaths(): void
    {
        $service = new PromotionCampaignService();
        $ended = new PromotionCampaign(
            'ended',
            'Ended',
            ['p'],
            endsAt: new \DateTimeImmutable('2026-09-21T00:00:00+00:00'),
            status: PromotionCampaignStatus::Active,
        );

        $evaluation = $service->evaluate($ended, new \DateTimeImmutable('2026-09-22T00:00:00+00:00'));
        self::assertFalse($evaluation->available);
        self::assertContains('campaign_ended', $evaluation->reasons);

        $this->expectException(\InvalidArgumentException::class);
        $service->recordSpend($ended, -1);
    }

    public function testCouponIssuanceRejectsExpiredIssueAndMissingDeactivate(): void
    {
        $service = new PromotionCouponIssuanceService();

        try {
            $service->issue(
                new PromotionCouponBook(),
                'late',
                'p',
                new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
                endsAt: new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
            );
            self::fail('Expired coupon issuance must fail.');
        } catch (\DomainException $exception) {
            self::assertSame('Coupon cannot be issued after its expiration time.', $exception->getMessage());
        }

        $this->expectException(\DomainException::class);
        $service->deactivate(new PromotionCouponBook(), 'missing');
    }

    public function testCouponValidationCoversLifecycleAndTimeFailures(): void
    {
        $service = new PromotionCouponService();
        $ledger = new PromotionRedemptionLedger();

        $inactive = new PromotionCoupon('inactive', 'p', status: PromotionCouponStatus::Inactive);
        self::assertSame(['coupon_inactive'], $service->validate($inactive, $ledger, 'customer')->reasons);

        $issuedLater = new PromotionCoupon(
            'issued',
            'p',
            issuedAt: new \DateTimeImmutable('2026-09-23T00:00:00+00:00'),
        );
        self::assertContains(
            'coupon_not_issued_yet',
            $service->validate(
                $issuedLater,
                $ledger,
                'customer',
                new \DateTimeImmutable('2026-09-22T00:00:00+00:00'),
            )->reasons,
        );

        $startsLater = new PromotionCoupon(
            'starts',
            'p',
            startsAt: new \DateTimeImmutable('2026-09-23T00:00:00+00:00'),
        );
        self::assertContains(
            'coupon_not_started',
            $service->validate(
                $startsLater,
                $ledger,
                'customer',
                new \DateTimeImmutable('2026-09-22T00:00:00+00:00'),
            )->reasons,
        );

        $ended = new PromotionCoupon(
            'ended',
            'p',
            endsAt: new \DateTimeImmutable('2026-09-21T00:00:00+00:00'),
        );
        self::assertContains(
            'coupon_ended',
            $service->validate(
                $ended,
                $ledger,
                'customer',
                new \DateTimeImmutable('2026-09-22T00:00:00+00:00'),
            )->reasons,
        );
    }

    public function testCouponServiceRejectsEmptyIdentifiers(): void
    {
        $service = new PromotionCouponService();
        $coupon = new PromotionCoupon('code', 'p');

        try {
            $service->validate($coupon, new PromotionRedemptionLedger(), '');
            self::fail('Empty customer id must fail.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Customer id cannot be empty.', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $service->redeem($coupon, new PromotionRedemptionLedger(), 'customer', '');
    }

    public function testRedemptionLedgerMissReplayCountsAndReverseNoOp(): void
    {
        $active = new PromotionRedemption('code', 'customer-1', 'order-1');
        $reversed = new PromotionRedemption(
            'code',
            'customer-2',
            'order-2',
            PromotionRedemptionStatus::Reversed,
        );
        $ledger = new PromotionRedemptionLedger([$active, $reversed]);

        self::assertSame(1, $ledger->redeemedCount(' CODE '));
        self::assertSame(1, $ledger->redeemedCountForCustomer('code', 'customer-1'));
        self::assertSame(0, $ledger->redeemedCountForCustomer('code', 'customer-2'));
        self::assertSame($active, $ledger->findActive('CODE', 'customer-1', 'order-1'));
        self::assertNull($ledger->findActive('CODE', 'customer-2', 'order-2'));
        self::assertSame($ledger, $ledger->redeem($active));
        self::assertSame($ledger, $ledger->reverse('CODE', 'missing', 'missing'));
    }
}
