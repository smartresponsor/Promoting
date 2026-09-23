<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionCouponRedemptionResultDTO;
use App\Promoting\DTO\PromotionCouponValidationDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Enum\PromotionActivationMode;
use App\Promoting\Enum\PromotionStackingMode;
use App\Promoting\Service\PromotionApplicationService;
use App\Promoting\Service\PromotionBenefitService;
use App\Promoting\Service\PromotionCampaignSelectionService;
use App\Promoting\Service\PromotionCampaignService;
use App\Promoting\Service\PromotionCheckoutApplicationService;
use App\Promoting\Service\PromotionCheckoutPlanService;
use App\Promoting\Service\PromotionCouponResolutionService;
use App\Promoting\Service\PromotionCouponService;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\Service\PromotionResolutionService;
use App\Promoting\Service\PromotionSelectionService;
use App\Promoting\ServiceInterface\PromotionCouponServiceInterface;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCoupon;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemption;
use App\Promoting\ValueObject\PromotionRedemptionLedger;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Verifies checkout application mutates only coupon redemption state after unified resolution. */
final class PromotionCheckoutApplicationServiceTest extends TestCase
{
    private function service(): PromotionCheckoutApplicationService
    {
        $evaluation = new PromotionEvaluationService();
        $coupon = new PromotionCouponService();
        $application = new PromotionApplicationService($evaluation);
        $selection = new PromotionSelectionService($evaluation);
        $campaign = new PromotionCampaignService();
        $plan = new PromotionCheckoutPlanService(
            $selection,
            new PromotionCampaignSelectionService($campaign, $selection),
            new PromotionCouponResolutionService($coupon, $evaluation),
            new PromotionResolutionService($application),
            new PromotionBenefitService($evaluation),
        );

        return new PromotionCheckoutApplicationService($plan, $coupon);
    }

    private function request(): PromotionEvaluationRequestDTO
    {
        return new PromotionEvaluationRequestDTO(
            1000,
            'USD',
            new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
        );
    }

    private function benefitRequest(): PromotionBenefitRequestDTO
    {
        return new PromotionBenefitRequestDTO(
            1000,
            'USD',
            [],
            new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
        );
    }

    public function testCouponIsRedeemedWhenCouponPromotionParticipates(): void
    {
        $automatic = new Promotion(
            'automatic',
            'Automatic',
            new PromotionRule(),
            PromotionAction::fixed(100),
            priority: 10,
        );
        $couponPromotion = new Promotion(
            'coupon',
            'Coupon',
            new PromotionRule(),
            PromotionAction::fixed(200),
            priority: 100,
            activationMode: PromotionActivationMode::Coupon,
        );
        $coupon = new PromotionCoupon('SAVE', 'coupon', usageLimit: 1, perCustomerLimit: 1);

        $result = $this->service()->apply(
            new PromotionCatalog([$automatic, $couponPromotion]),
            new PromotionCouponBook([$coupon]),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'save',
            'customer',
            'order-1',
        );

        self::assertNotNull($result->couponRedemption);
        self::assertTrue($result->couponRedemption->redeemed);
        self::assertSame(1, $result->ledger->redeemedCount('SAVE'));
        self::assertContains('checkout_coupon_redeemed', $result->reasons);
        self::assertSame(300, $result->plan->resolution->totalDiscountAmountMinor);
    }

    public function testCouponIsNotRedeemedWhenHigherPriorityExclusiveAutomaticPromotionStopsResolution(): void
    {
        $automatic = new Promotion(
            'exclusive-auto',
            'Exclusive Automatic',
            new PromotionRule(),
            PromotionAction::fixed(300),
            priority: 100,
            stackingMode: PromotionStackingMode::Exclusive,
        );
        $couponPromotion = new Promotion(
            'coupon',
            'Coupon',
            new PromotionRule(),
            PromotionAction::fixed(200),
            priority: 10,
            activationMode: PromotionActivationMode::Coupon,
        );
        $coupon = new PromotionCoupon('SAVE', 'coupon');

        $result = $this->service()->apply(
            new PromotionCatalog([$automatic, $couponPromotion]),
            new PromotionCouponBook([$coupon]),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'save',
            'customer',
            'order-1',
        );

        self::assertNull($result->couponRedemption);
        self::assertSame(0, $result->ledger->redeemedCount('SAVE'));
        self::assertSame(['checkout_coupon_not_reached_by_resolution'], $result->reasons);
        self::assertCount(1, $result->plan->resolution->applications);
        self::assertSame('exclusive-auto', $result->plan->resolution->applications[0]->promotionId);
    }

    public function testSameOrderReplayIsIdempotentEvenAtUsageLimit(): void
    {
        $couponPromotion = new Promotion(
            'coupon',
            'Coupon',
            new PromotionRule(),
            PromotionAction::fixed(200),
            activationMode: PromotionActivationMode::Coupon,
        );
        $coupon = new PromotionCoupon('ONCE', 'coupon', usageLimit: 1, perCustomerLimit: 1);
        $catalog = new PromotionCatalog([$couponPromotion]);
        $book = new PromotionCouponBook([$coupon]);

        $first = $this->service()->apply(
            $catalog,
            $book,
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'once',
            'customer',
            'order-1',
        );
        $replay = $this->service()->apply(
            $catalog,
            $book,
            $first->ledger,
            $this->request(),
            $this->benefitRequest(),
            'once',
            'customer',
            'order-1',
        );

        self::assertNotNull($replay->couponRedemption);
        self::assertTrue($replay->couponRedemption->redeemed);
        self::assertSame(['coupon_redemption_idempotent_replay'], $replay->couponRedemption->reasons);
        self::assertSame(1, $replay->ledger->redeemedCount('ONCE'));
    }

    public function testInvalidCouponLeavesLedgerUnchanged(): void
    {
        $ledger = new PromotionRedemptionLedger();

        $result = $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            $ledger,
            $this->request(),
            $this->benefitRequest(),
            'missing',
            'customer',
            'order-1',
        );

        self::assertSame($ledger, $result->ledger);
        self::assertNull($result->couponRedemption);
        self::assertContains('coupon_not_found', $result->reasons);
        self::assertContains('checkout_coupon_not_redeemed', $result->reasons);
    }

    public function testCheckoutWithoutCouponLeavesLedgerUnchanged(): void
    {
        $automatic = new Promotion(
            'automatic',
            'Automatic',
            new PromotionRule(),
            PromotionAction::fixed(100),
        );
        $ledger = new PromotionRedemptionLedger();

        $result = $this->service()->apply(
            new PromotionCatalog([$automatic]),
            new PromotionCouponBook(),
            $ledger,
            $this->request(),
            $this->benefitRequest(),
        );

        self::assertSame($ledger, $result->ledger);
        self::assertNull($result->couponRedemption);
        self::assertSame(['checkout_application_completed_without_coupon'], $result->reasons);
        self::assertSame(100, $result->plan->resolution->totalDiscountAmountMinor);
    }

    public function testCheckoutCouponReversalReleasesRedemption(): void
    {
        $coupon = new PromotionCoupon('SAVE', 'coupon');
        $ledger = new PromotionRedemptionLedger([
            new PromotionRedemption('SAVE', 'customer', 'order-1'),
        ]);

        $result = $this->service()->reverseCoupon(
            new PromotionCouponBook([$coupon]),
            $ledger,
            'save',
            'customer',
            'order-1',
        );

        self::assertNotNull($result->couponReversal);
        self::assertTrue($result->couponReversal->redeemed);
        self::assertSame(0, $result->ledger->redeemedCount('SAVE'));
        self::assertSame(
            ['coupon_redemption_reversed', 'checkout_coupon_reversal_completed'],
            $result->reasons,
        );
    }

    public function testCheckoutCouponReversalReplayIsIdempotentNoop(): void
    {
        $coupon = new PromotionCoupon('SAVE', 'coupon');
        $ledger = new PromotionRedemptionLedger();

        $result = $this->service()->reverseCoupon(
            new PromotionCouponBook([$coupon]),
            $ledger,
            'SAVE',
            'customer',
            'order-1',
        );

        self::assertSame($ledger, $result->ledger);
        self::assertNotNull($result->couponReversal);
        self::assertFalse($result->couponReversal->redeemed);
        self::assertSame(
            ['coupon_reversal_idempotent_noop', 'checkout_coupon_reversal_idempotent_noop'],
            $result->reasons,
        );
    }

    public function testCheckoutCouponReversalMissingCouponLeavesLedgerUnchanged(): void
    {
        $ledger = new PromotionRedemptionLedger();

        $result = $this->service()->reverseCoupon(
            new PromotionCouponBook(),
            $ledger,
            'MISSING',
            'customer',
            'order-1',
        );

        self::assertSame($ledger, $result->ledger);
        self::assertNull($result->couponReversal);
        self::assertSame(['checkout_coupon_reversal_coupon_not_found'], $result->reasons);
    }

    public function testCheckoutCouponReversalRejectsBlankCouponCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->reverseCoupon(
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            '   ',
            'customer',
            'order-1',
        );
    }

    public function testCheckoutCouponReversalRejectsBlankCustomerId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->reverseCoupon(
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            'SAVE',
            '   ',
            'order-1',
        );
    }

    public function testCheckoutCouponReversalRejectsBlankOrderId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->reverseCoupon(
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            'SAVE',
            'customer',
            '   ',
        );
    }

    public function testCouponCodeRequiresCustomerIdentity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'SAVE',
            null,
            'order-1',
        );
    }

    public function testRedemptionBackendFailureKeepsLedgerUnchanged(): void
    {
        $evaluation = new PromotionEvaluationService();
        $planningCouponService = new PromotionCouponService();
        $application = new PromotionApplicationService($evaluation);
        $selection = new PromotionSelectionService($evaluation);
        $campaign = new PromotionCampaignService();
        $plan = new PromotionCheckoutPlanService(
            $selection,
            new PromotionCampaignSelectionService($campaign, $selection),
            new PromotionCouponResolutionService($planningCouponService, $evaluation),
            new PromotionResolutionService($application),
            new PromotionBenefitService($evaluation),
        );
        $failingCouponService = new class implements PromotionCouponServiceInterface {
            public function validate(
                PromotionCoupon $coupon,
                PromotionRedemptionLedger $ledger,
                string $customerId,
                ?\DateTimeImmutable $at = null,
            ): PromotionCouponValidationDTO {
                return new PromotionCouponValidationDTO(true, ['synthetic_valid']);
            }

            public function redeem(
                PromotionCoupon $coupon,
                PromotionRedemptionLedger $ledger,
                string $customerId,
                string $orderId,
                ?\DateTimeImmutable $at = null,
            ): PromotionCouponRedemptionResultDTO {
                return new PromotionCouponRedemptionResultDTO(
                    false,
                    $ledger,
                    null,
                    ['synthetic_redemption_failure'],
                );
            }

            public function reverse(
                PromotionCoupon $coupon,
                PromotionRedemptionLedger $ledger,
                string $customerId,
                string $orderId,
            ): PromotionCouponRedemptionResultDTO {
                return new PromotionCouponRedemptionResultDTO(false, $ledger, null, ['synthetic_reverse']);
            }
        };
        $service = new PromotionCheckoutApplicationService($plan, $failingCouponService);
        $couponPromotion = new Promotion(
            'coupon',
            'Coupon',
            new PromotionRule(),
            PromotionAction::fixed(200),
            activationMode: PromotionActivationMode::Coupon,
        );
        $coupon = new PromotionCoupon('SAVE', 'coupon');
        $ledger = new PromotionRedemptionLedger();

        $result = $service->apply(
            new PromotionCatalog([$couponPromotion]),
            new PromotionCouponBook([$coupon]),
            $ledger,
            $this->request(),
            $this->benefitRequest(),
            'SAVE',
            'customer',
            'order-1',
        );

        self::assertSame($ledger, $result->ledger);
        self::assertNotNull($result->couponRedemption);
        self::assertFalse($result->couponRedemption->redeemed);
        self::assertSame(
            ['synthetic_redemption_failure', 'checkout_coupon_redemption_failed'],
            $result->reasons,
        );
    }

    public function testCouponCodeRejectsBlankCustomerIdentity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'SAVE',
            '   ',
            'order-1',
        );
    }

    public function testCouponCodeRejectsBlankOrderIdentity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'SAVE',
            'customer',
            '   ',
        );
    }

    public function testCouponCodeRequiresOrderIdentity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'SAVE',
            'customer',
        );
    }
}
