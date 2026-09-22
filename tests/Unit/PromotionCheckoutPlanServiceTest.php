<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Enum\PromotionActivationMode;
use App\Promoting\Enum\PromotionStackingMode;
use App\Promoting\Service\PromotionApplicationService;
use App\Promoting\Service\PromotionBenefitService;
use App\Promoting\Service\PromotionCheckoutPlanService;
use App\Promoting\Service\PromotionCouponResolutionService;
use App\Promoting\Service\PromotionCouponService;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\Service\PromotionResolutionService;
use App\Promoting\Service\PromotionSelectionService;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionBenefit;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCoupon;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Verifies read-only checkout planning across automatic, coupon, stacking, and benefit semantics. */
final class PromotionCheckoutPlanServiceTest extends TestCase
{
    private function service(): PromotionCheckoutPlanService
    {
        $evaluation = new PromotionEvaluationService();
        $coupon = new PromotionCouponService();
        $application = new PromotionApplicationService($evaluation);

        return new PromotionCheckoutPlanService(
            new PromotionSelectionService($evaluation),
            new PromotionCouponResolutionService($coupon, $evaluation),
            new PromotionResolutionService($application),
            new PromotionBenefitService($evaluation),
        );
    }

    private function request(int $subtotalMinor = 1000): PromotionEvaluationRequestDTO
    {
        return new PromotionEvaluationRequestDTO(
            $subtotalMinor,
            'USD',
            new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
        );
    }

    /** @param array<string, int> $itemQuantities */
    private function benefitRequest(int $subtotalMinor = 1000, array $itemQuantities = []): PromotionBenefitRequestDTO
    {
        return new PromotionBenefitRequestDTO(
            $subtotalMinor,
            'USD',
            $itemQuantities,
            new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
        );
    }

    public function testCouponAndAutomaticPromotionsShareOnePriorityAndExclusivityResolution(): void
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
            PromotionAction::fixed(250),
            priority: 100,
            stackingMode: PromotionStackingMode::Exclusive,
            activationMode: PromotionActivationMode::Coupon,
        );
        $coupon = new PromotionCoupon('SAVE', 'coupon');

        $result = $this->service()->plan(
            new PromotionCatalog([$automatic, $couponPromotion]),
            new PromotionCouponBook([$coupon]),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'save',
            'customer',
        );

        self::assertSame(250, $result->resolution->totalDiscountAmountMinor);
        self::assertCount(1, $result->resolution->applications);
        self::assertSame('coupon', $result->resolution->applications[0]->promotionId);
        self::assertNotNull($result->couponResolution);
        self::assertTrue($result->couponResolution->eligible);
        self::assertContains('coupon_promotion_included', $result->reasons);
    }

    public function testInvalidCouponFallsBackToAutomaticPlanWithoutMutatingLedger(): void
    {
        $automatic = new Promotion(
            'automatic',
            'Automatic',
            new PromotionRule(),
            PromotionAction::fixed(100),
        );
        $ledger = new PromotionRedemptionLedger();

        $result = $this->service()->plan(
            new PromotionCatalog([$automatic]),
            new PromotionCouponBook(),
            $ledger,
            $this->request(),
            $this->benefitRequest(),
            'missing',
            'customer',
        );

        self::assertSame(100, $result->resolution->totalDiscountAmountMinor);
        self::assertNotNull($result->couponResolution);
        self::assertFalse($result->couponResolution->eligible);
        self::assertContains('coupon_promotion_not_included', $result->reasons);
        self::assertSame(0, $ledger->redeemedCount('missing'));
    }

    public function testBenefitsAreProducedOnlyForPromotionsReachedByResolver(): void
    {
        $exclusiveGift = new Promotion(
            'gift',
            'Gift',
            new PromotionRule(),
            PromotionAction::fixed(0),
            priority: 100,
            stackingMode: PromotionStackingMode::Exclusive,
            benefit: PromotionBenefit::freeGift('GIFT'),
        );
        $shipping = new Promotion(
            'shipping',
            'Shipping',
            new PromotionRule(),
            PromotionAction::fixed(0),
            priority: 10,
            benefit: PromotionBenefit::freeShipping(),
        );

        $result = $this->service()->plan(
            new PromotionCatalog([$shipping, $exclusiveGift]),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
        );

        self::assertCount(1, $result->resolution->applications);
        self::assertCount(1, $result->benefits);
        self::assertSame('gift', $result->benefits[0]->promotionId);
        self::assertSame('GIFT', $result->benefits[0]->rewardSku);
    }

    public function testCouponResolutionDoesNotDuplicateAlreadyAutomaticPromotion(): void
    {
        $promotion = new Promotion(
            'shared',
            'Shared',
            new PromotionRule(),
            PromotionAction::fixed(100),
            priority: 10,
        );
        $coupon = new PromotionCoupon('SHARED', 'shared');

        $result = $this->service()->plan(
            new PromotionCatalog([$promotion]),
            new PromotionCouponBook([$coupon]),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'shared',
            'customer',
        );

        self::assertCount(1, $result->resolution->applications);
        self::assertSame(100, $result->resolution->totalDiscountAmountMinor);
    }

    public function testCouponCodeRequiresCustomerIdentity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->plan(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'SAVE',
        );
    }

    public function testPlanRejectsMismatchedCurrencyContexts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->plan(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            new PromotionBenefitRequestDTO(
                1000,
                'EUR',
                [],
                new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
            ),
        );
    }

    public function testPlanRejectsMismatchedTimeContexts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->plan(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            new PromotionBenefitRequestDTO(
                1000,
                'USD',
                [],
                new \DateTimeImmutable('2026-09-22T12:00:01+00:00'),
            ),
        );
    }

    public function testPlanRejectsMismatchedCheckoutContexts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->plan(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(1000),
            $this->benefitRequest(999),
        );
    }
}
