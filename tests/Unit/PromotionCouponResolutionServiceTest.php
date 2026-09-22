<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionEvaluationRequestDTO;
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

/** Verifies coupon-to-promotion lookup and eligibility resolution. */
final class PromotionCouponResolutionServiceTest extends TestCase
{
    public function testUnknownCouponReturnsAuditableMiss(): void
    {
        $service = new PromotionCouponResolutionService(
            new PromotionCouponService(),
            new PromotionEvaluationService(),
        );

        $result = $service->resolve(
            new PromotionCouponBook(),
            new PromotionCatalog(),
            new PromotionRedemptionLedger(),
            'missing',
            'customer-1',
            new PromotionEvaluationRequestDTO(1000, 'USD'),
        );

        self::assertFalse($result->eligible);
        self::assertNull($result->coupon);
        self::assertNull($result->promotion);
        self::assertSame(['coupon_not_found'], $result->reasons);
    }

    public function testValidCouponResolvesLinkedEligiblePromotion(): void
    {
        $service = new PromotionCouponResolutionService(
            new PromotionCouponService(),
            new PromotionEvaluationService(),
        );

        $coupon = new PromotionCoupon('save', 'promo-save');
        $promotion = new Promotion(
            'promo-save',
            'Save',
            new PromotionRule(),
            PromotionAction::fixed(100),
        );

        $result = $service->resolve(
            new PromotionCouponBook([$coupon]),
            new PromotionCatalog([$promotion]),
            new PromotionRedemptionLedger(),
            'SAVE',
            'customer-1',
            new PromotionEvaluationRequestDTO(1000, 'USD'),
        );

        self::assertTrue($result->eligible);
        self::assertSame($coupon, $result->coupon);
        self::assertSame($promotion, $result->promotion);
        self::assertSame(
            [
                'coupon_active',
                'coupon_audience_unrestricted',
                'coupon_usage_limit_available',
                'coupon_customer_limit_available',
                'coupon_valid',
                'promotion_active',
                'promotion_eligible',
            ],
            $result->reasons,
        );
    }

    public function testMissingLinkedPromotionIsReportedAfterCouponValidation(): void
    {
        $service = new PromotionCouponResolutionService(
            new PromotionCouponService(),
            new PromotionEvaluationService(),
        );

        $coupon = new PromotionCoupon('orphan', 'missing-promotion');
        $result = $service->resolve(
            new PromotionCouponBook([$coupon]),
            new PromotionCatalog(),
            new PromotionRedemptionLedger(),
            'orphan',
            'customer-1',
            new PromotionEvaluationRequestDTO(1000, 'USD'),
        );

        self::assertFalse($result->eligible);
        self::assertSame($coupon, $result->coupon);
        self::assertNull($result->promotion);
        self::assertSame(
            [
                'coupon_active',
                'coupon_audience_unrestricted',
                'coupon_usage_limit_available',
                'coupon_customer_limit_available',
                'coupon_valid',
                'coupon_promotion_not_found',
            ],
            $result->reasons,
        );
    }

    public function testCustomerBoundCouponRejectsDifferentCustomerBeforePromotionLookup(): void
    {
        $service = new PromotionCouponResolutionService(
            new PromotionCouponService(),
            new PromotionEvaluationService(),
        );

        $coupon = new PromotionCoupon(
            'vip',
            'promo-vip',
            customerId: 'customer-vip',
        );

        $result = $service->resolve(
            new PromotionCouponBook([$coupon]),
            new PromotionCatalog(),
            new PromotionRedemptionLedger(),
            'vip',
            'customer-other',
            new PromotionEvaluationRequestDTO(1000, 'USD'),
        );

        self::assertFalse($result->eligible);
        self::assertNull($result->promotion);
        self::assertSame(['coupon_active', 'coupon_customer_mismatch'], $result->reasons);
    }
}
