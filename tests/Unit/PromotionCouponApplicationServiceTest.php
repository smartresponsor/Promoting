<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Service\PromotionApplicationService;
use App\Promoting\Service\PromotionCouponApplicationService;
use App\Promoting\Service\PromotionCouponResolutionService;
use App\Promoting\Service\PromotionCouponService;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCoupon;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Verifies end-to-end coupon resolve, application, and redemption semantics. */
final class PromotionCouponApplicationServiceTest extends TestCase
{
    private function service(): PromotionCouponApplicationService
    {
        $evaluation = new PromotionEvaluationService();
        $coupon = new PromotionCouponService();

        return new PromotionCouponApplicationService(
            new PromotionCouponResolutionService($coupon, $evaluation),
            new PromotionApplicationService($evaluation),
            $coupon,
        );
    }

    public function testEligibleCouponAppliesPromotionAndRecordsRedemption(): void
    {
        $coupon = new PromotionCoupon('save10', 'promo-save10', usageLimit: 2, perCustomerLimit: 1);
        $promotion = new Promotion(
            'promo-save10',
            'Save 10',
            new PromotionRule(),
            PromotionAction::fixed(100),
        );

        $result = $this->service()->apply(
            new PromotionCouponBook([$coupon]),
            new PromotionCatalog([$promotion]),
            new PromotionRedemptionLedger(),
            'save10',
            'customer-1',
            'order-1',
            new PromotionEvaluationRequestDTO(1000, 'USD'),
        );

        self::assertTrue($result->applied);
        self::assertNotNull($result->application);
        self::assertSame(100, $result->application->discountAmountMinor);
        self::assertNotNull($result->redemption);
        self::assertTrue($result->redemption->redeemed);
        self::assertSame(1, $result->ledger->redeemedCount('SAVE10'));
        self::assertContains('coupon_application_applied', $result->reasons);
    }

    public function testIdempotentReplayDoesNotDuplicateRedemption(): void
    {
        $coupon = new PromotionCoupon('once', 'promo-once', usageLimit: 1, perCustomerLimit: 1);
        $promotion = new Promotion(
            'promo-once',
            'Once',
            new PromotionRule(),
            PromotionAction::fixed(100),
        );
        $book = new PromotionCouponBook([$coupon]);
        $catalog = new PromotionCatalog([$promotion]);
        $request = new PromotionEvaluationRequestDTO(1000, 'USD');

        $first = $this->service()->apply(
            $book,
            $catalog,
            new PromotionRedemptionLedger(),
            'once',
            'customer-1',
            'order-1',
            $request,
        );
        $replay = $this->service()->apply(
            $book,
            $catalog,
            $first->ledger,
            'once',
            'customer-1',
            'order-1',
            $request,
        );

        self::assertTrue($replay->applied);
        self::assertSame(1, $replay->ledger->redeemedCount('ONCE'));
        self::assertNotNull($replay->redemption);
        self::assertSame(['coupon_redemption_idempotent_replay'], $replay->redemption->reasons);
    }

    public function testInvalidCouponDoesNotApplyOrRedeem(): void
    {
        $promotion = new Promotion(
            'promo-save',
            'Save',
            new PromotionRule(),
            PromotionAction::fixed(100),
        );
        $ledger = new PromotionRedemptionLedger();

        $result = $this->service()->apply(
            new PromotionCouponBook(),
            new PromotionCatalog([$promotion]),
            $ledger,
            'missing',
            'customer-1',
            'order-1',
            new PromotionEvaluationRequestDTO(1000, 'USD'),
        );

        self::assertFalse($result->applied);
        self::assertSame($ledger, $result->ledger);
        self::assertNull($result->application);
        self::assertNull($result->redemption);
        self::assertSame(['coupon_not_found', 'coupon_application_not_applied'], $result->reasons);
    }
}
