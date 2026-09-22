<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionApplicationResultDTO;
use App\Promoting\DTO\PromotionCouponRedemptionResultDTO;
use App\Promoting\DTO\PromotionCouponResolutionResultDTO;
use App\Promoting\DTO\PromotionCouponValidationDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Enum\PromotionApplicationMethod;
use App\Promoting\Service\PromotionApplicationService;
use App\Promoting\Service\PromotionCouponApplicationService;
use App\Promoting\Service\PromotionCouponResolutionService;
use App\Promoting\Service\PromotionCouponService;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\ServiceInterface\PromotionApplicationServiceInterface;
use App\Promoting\ServiceInterface\PromotionCouponResolutionServiceInterface;
use App\Promoting\ServiceInterface\PromotionCouponServiceInterface;
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

    private function serviceWithResolution(
        PromotionCouponResolutionResultDTO $resolution,
    ): PromotionCouponApplicationService {
        $evaluation = new PromotionEvaluationService();
        $coupon = new PromotionCouponService();
        $resolver = new class($resolution) implements PromotionCouponResolutionServiceInterface {
            public function __construct(private PromotionCouponResolutionResultDTO $resolution)
            {
            }

            public function resolve(
                PromotionCouponBook $couponBook,
                PromotionCatalog $promotionCatalog,
                PromotionRedemptionLedger $ledger,
                string $couponCode,
                string $customerId,
                PromotionEvaluationRequestDTO $request,
            ): PromotionCouponResolutionResultDTO {
                return $this->resolution;
            }
        };

        return new PromotionCouponApplicationService(
            $resolver,
            new PromotionApplicationService($evaluation),
            $coupon,
        );
    }

    public function testEligibleResolutionWithoutCouponIsRejectedDefensively(): void
    {
        $result = $this->serviceWithResolution(
            new PromotionCouponResolutionResultDTO(true, null, null, ['synthetic']),
        )->apply(
            new PromotionCouponBook(),
            new PromotionCatalog(),
            new PromotionRedemptionLedger(),
            'code',
            'customer',
            'order',
            new PromotionEvaluationRequestDTO(100, 'USD'),
        );

        self::assertFalse($result->applied);
        self::assertContains('coupon_application_not_applied', $result->reasons);
    }

    public function testEligibleResolutionWithoutPromotionIsRejectedDefensively(): void
    {
        $coupon = new PromotionCoupon('code', 'p');
        $result = $this->serviceWithResolution(
            new PromotionCouponResolutionResultDTO(true, $coupon, null, ['synthetic']),
        )->apply(
            new PromotionCouponBook([$coupon]),
            new PromotionCatalog(),
            new PromotionRedemptionLedger(),
            'code',
            'customer',
            'order',
            new PromotionEvaluationRequestDTO(100, 'USD'),
        );

        self::assertFalse($result->applied);
        self::assertContains('coupon_application_not_applied', $result->reasons);
    }

    public function testIneligibleApplicationAfterSuccessfulResolutionDoesNotRedeem(): void
    {
        $coupon = new PromotionCoupon('code', 'promo');
        $promotion = new Promotion('promo', 'Promo', new PromotionRule(), PromotionAction::fixed(10));
        $resolver = new class($coupon, $promotion) implements PromotionCouponResolutionServiceInterface {
            public function __construct(
                private PromotionCoupon $coupon,
                private Promotion $promotion,
            ) {
            }

            public function resolve(
                PromotionCouponBook $couponBook,
                PromotionCatalog $promotionCatalog,
                PromotionRedemptionLedger $ledger,
                string $couponCode,
                string $customerId,
                PromotionEvaluationRequestDTO $request,
            ): PromotionCouponResolutionResultDTO {
                return new PromotionCouponResolutionResultDTO(
                    true,
                    $this->coupon,
                    $this->promotion,
                    ['resolved'],
                );
            }
        };
        $application = new class implements PromotionApplicationServiceInterface {
            public function apply(
                Promotion $promotion,
                PromotionEvaluationRequestDTO $request,
            ): PromotionApplicationResultDTO {
                return new PromotionApplicationResultDTO(
                    $promotion->id,
                    false,
                    PromotionApplicationMethod::Fixed,
                    $request->subtotalMinor,
                    0,
                    $request->subtotalMinor,
                    ['synthetic_application_rejected'],
                );
            }
        };
        $ledger = new PromotionRedemptionLedger();

        $result = (new PromotionCouponApplicationService(
            $resolver,
            $application,
            new PromotionCouponService(),
        ))->apply(
            new PromotionCouponBook([$coupon]),
            new PromotionCatalog([$promotion]),
            $ledger,
            'code',
            'customer',
            'order',
            new PromotionEvaluationRequestDTO(100, 'USD'),
        );

        self::assertFalse($result->applied);
        self::assertSame($ledger, $result->ledger);
        self::assertNotNull($result->application);
        self::assertNull($result->redemption);
        self::assertContains('coupon_application_not_applied', $result->reasons);
    }

    public function testRedemptionFailureAfterSuccessfulApplicationLeavesLedgerUntouched(): void
    {
        $coupon = new PromotionCoupon('code', 'promo');
        $promotion = new Promotion('promo', 'Promo', new PromotionRule(), PromotionAction::fixed(10));
        $resolver = new class($coupon, $promotion) implements PromotionCouponResolutionServiceInterface {
            public function __construct(
                private PromotionCoupon $coupon,
                private Promotion $promotion,
            ) {
            }

            public function resolve(
                PromotionCouponBook $couponBook,
                PromotionCatalog $promotionCatalog,
                PromotionRedemptionLedger $ledger,
                string $couponCode,
                string $customerId,
                PromotionEvaluationRequestDTO $request,
            ): PromotionCouponResolutionResultDTO {
                return new PromotionCouponResolutionResultDTO(
                    true,
                    $this->coupon,
                    $this->promotion,
                    ['resolved'],
                );
            }
        };
        $couponService = new class implements PromotionCouponServiceInterface {
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
                    ['synthetic_redemption_rejected'],
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
        $ledger = new PromotionRedemptionLedger();

        $result = (new PromotionCouponApplicationService(
            $resolver,
            new PromotionApplicationService(new PromotionEvaluationService()),
            $couponService,
        ))->apply(
            new PromotionCouponBook([$coupon]),
            new PromotionCatalog([$promotion]),
            $ledger,
            'code',
            'customer',
            'order',
            new PromotionEvaluationRequestDTO(100, 'USD'),
        );

        self::assertFalse($result->applied);
        self::assertSame($ledger, $result->ledger);
        self::assertNotNull($result->application);
        self::assertNotNull($result->redemption);
        self::assertContains('coupon_application_not_redeemed', $result->reasons);
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
