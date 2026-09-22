<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Enum\PromotionCouponStatus;
use App\Promoting\Enum\PromotionStatus;
use App\Promoting\Service\PromotionBenefitService;
use App\Promoting\Service\PromotionCouponIssuanceService;
use App\Promoting\Service\PromotionCouponService;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionBenefit;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCoupon;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemption;
use App\Promoting\ValueObject\PromotionRedemptionLedger;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Completes remaining reachable branch combinations in core promotion services and immutable collections. */
final class PromotionCoverageFinalizationTest extends TestCase
{
    public function testEvaluationCoversEndOnlyWindowAndMinimumSubtotalFailure(): void
    {
        $service = new PromotionEvaluationService();
        $ended = new Promotion(
            'ended',
            'Ended',
            new PromotionRule(),
            PromotionAction::fixed(1),
            endsAt: new \DateTimeImmutable('2026-09-21T00:00:00+00:00'),
        );

        self::assertSame(
            ['promotion_active', 'promotion_start_window_met', 'promotion_ended'],
            $service->evaluate(
                $ended,
                new PromotionEvaluationRequestDTO(
                    100,
                    'USD',
                    new \DateTimeImmutable('2026-09-22T00:00:00+00:00'),
                ),
            )->reasons,
        );

        $minimum = new Promotion(
            'minimum',
            'Minimum',
            new PromotionRule([\App\Promoting\ValueObject\PromotionCondition::minimumSubtotal(101)]),
            PromotionAction::fixed(1),
        );
        self::assertSame(
            ['promotion_active', 'minimum_subtotal_not_met'],
            $service->evaluate($minimum, new PromotionEvaluationRequestDTO(100, 'USD'))->reasons,
        );

        $inactive = $minimum->withStatus(PromotionStatus::Inactive);
        self::assertSame(['promotion_inactive'], $service->evaluate($inactive, new PromotionEvaluationRequestDTO(100, 'USD'))->reasons);
    }

    public function testBenefitServiceCoversMissingAndIneligibleBenefitPaths(): void
    {
        $service = new PromotionBenefitService(new PromotionEvaluationService());
        $request = new PromotionBenefitRequestDTO(100, 'USD', []);

        $missing = new Promotion('missing', 'Missing', new PromotionRule(), PromotionAction::fixed(0));
        self::assertSame(
            ['promotion_active', 'promotion_eligible', 'promotion_benefit_missing'],
            $service->evaluate($missing, $request)->reasons,
        );

        $inactive = new Promotion(
            'inactive',
            'Inactive',
            new PromotionRule(),
            PromotionAction::fixed(0),
            status: PromotionStatus::Inactive,
            benefit: PromotionBenefit::freeShipping(),
        );
        self::assertSame(
            ['promotion_inactive', 'promotion_benefit_not_applied'],
            $service->evaluate($inactive, $request)->reasons,
        );
    }

    public function testCouponIssuanceCoversActiveInactiveAndValidExpirationBranches(): void
    {
        $service = new PromotionCouponIssuanceService();
        $issuedAt = new \DateTimeImmutable('2026-09-22T00:00:00+00:00');
        $endsAt = new \DateTimeImmutable('2026-09-23T00:00:00+00:00');
        $issued = $service->issue(
            new PromotionCouponBook(),
            'expiring',
            'p',
            $issuedAt,
            endsAt: $endsAt,
        );

        self::assertSame($endsAt, $issued->coupon->endsAt);

        $deactivated = $service->deactivate($issued->book, 'expiring');
        self::assertSame(PromotionCouponStatus::Inactive, $deactivated->coupon->status);
        self::assertSame(
            $deactivated->book,
            $service->deactivate($deactivated->book, 'expiring')->book,
        );
    }

    public function testCouponServiceCoversSuccessfulWindowedRedemptionAndReverse(): void
    {
        $service = new PromotionCouponService();
        $at = new \DateTimeImmutable('2026-09-22T12:00:00+00:00');
        $coupon = new PromotionCoupon(
            'window',
            'p',
            usageLimit: 2,
            perCustomerLimit: 2,
            issuedAt: new \DateTimeImmutable('2026-09-20T00:00:00+00:00'),
            startsAt: new \DateTimeImmutable('2026-09-21T00:00:00+00:00'),
            endsAt: new \DateTimeImmutable('2026-09-30T00:00:00+00:00'),
            customerId: 'customer',
        );

        $redeemed = $service->redeem(
            $coupon,
            new PromotionRedemptionLedger(),
            'customer',
            'order',
            $at,
        );
        self::assertTrue($redeemed->redeemed);
        self::assertNotNull($redeemed->redemption);

        $reversed = $service->reverse($coupon, $redeemed->ledger, 'customer', 'order');
        self::assertTrue($reversed->redeemed);
        self::assertSame(0, $reversed->ledger->redeemedCount('window'));
    }

    public function testCouponBookReplaceTraversesUnrelatedEntriesAndPreservesOrder(): void
    {
        $first = new PromotionCoupon('first', 'p');
        $target = new PromotionCoupon('target', 'p');
        $last = new PromotionCoupon('last', 'p');
        $book = new PromotionCouponBook([$first, $target, $last]);

        $replacement = $target->withStatus(PromotionCouponStatus::Inactive);
        $updated = $book->replace($replacement);

        self::assertSame($first, $updated->coupons[0]);
        self::assertSame($replacement, $updated->coupons[1]);
        self::assertSame($last, $updated->coupons[2]);
    }

    public function testCouponBookFindTraversesToLaterMatchingEntry(): void
    {
        $first = new PromotionCoupon('first', 'p');
        $second = new PromotionCoupon('second', 'p');
        $book = new PromotionCouponBook([$first, $second]);

        self::assertSame($second, $book->find('second'));
        self::assertNull($book->find('absent'));
    }

    public function testReversedIdempotencyKeyIsIgnoredByActiveLedgerOperations(): void
    {
        $reversed = new PromotionRedemption(
            'code',
            'customer',
            'order',
            \App\Promoting\Enum\PromotionRedemptionStatus::Reversed,
        );
        $ledger = new PromotionRedemptionLedger([$reversed]);

        self::assertNull($ledger->findActive('code', 'customer', 'order'));
        self::assertSame($ledger, $ledger->reverse('code', 'customer', 'order'));

        $redeemed = $ledger->redeem(new PromotionRedemption('code', 'customer', 'order'));
        self::assertSame(1, $redeemed->redeemedCount('code'));
    }

    public function testLedgerCountPredicatesCoverSameCouponDifferentCustomerAndViceVersa(): void
    {
        $entries = [
            new PromotionRedemption('code', 'customer-a', 'order-a'),
            new PromotionRedemption('code', 'customer-b', 'order-b'),
            new PromotionRedemption('other', 'customer-a', 'order-c'),
        ];
        $ledger = new PromotionRedemptionLedger($entries);

        self::assertSame(2, $ledger->redeemedCount('code'));
        self::assertSame(1, $ledger->redeemedCountForCustomer('code', 'customer-a'));
        self::assertSame(1, $ledger->redeemedCountForCustomer('code', 'customer-b'));
        self::assertSame(1, $ledger->redeemedCountForCustomer('other', 'customer-a'));
    }

    public function testOrderedConditionsCanPassThenFail(): void
    {
        $promotion = new Promotion(
            'ordered',
            'Ordered',
            new PromotionRule([
                \App\Promoting\ValueObject\PromotionCondition::minimumSubtotal(100),
                \App\Promoting\ValueObject\PromotionCondition::currency('EUR'),
            ]),
            PromotionAction::fixed(1),
        );

        $result = (new PromotionEvaluationService())->evaluate(
            $promotion,
            new PromotionEvaluationRequestDTO(100, 'USD'),
        );

        self::assertFalse($result->eligible);
        self::assertSame(
            ['promotion_active', 'minimum_subtotal_met', 'currency_mismatch'],
            $result->reasons,
        );
    }

    public function testCampaignRejectsEmptyLabelIndependentlyFromId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PromotionCampaign('campaign', '', ['p']);
    }

    public function testCompoundConstructorPathsAcceptDistinctValidStates(): void
    {
        $promotion = new Promotion(
            'end-only',
            'End only',
            new PromotionRule(),
            PromotionAction::fixed(1),
            endsAt: new \DateTimeImmutable('2026-10-01T00:00:00+00:00'),
        );
        self::assertSame('end-only', $promotion->id);

        $campaign = new PromotionCampaign('campaign', 'Campaign', ['one', 'two'], budgetMinor: 10, spentMinor: 5);
        self::assertSame(['one', 'two'], $campaign->promotionIds);

        $coupon = new PromotionCoupon(
            'code',
            'p',
            startsAt: new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
        );
        self::assertNotNull($coupon->startsAt);
    }
}
