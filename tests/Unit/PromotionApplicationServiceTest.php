<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Service\PromotionApplicationService;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionCondition;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Verifies deterministic promotion application semantics. */
final class PromotionApplicationServiceTest extends TestCase
{
    public function testFixedDiscountAppliesWhenConditionsMatch(): void
    {
        $promotion = new Promotion(
            'welcome',
            'Welcome',
            new PromotionRule([
                PromotionCondition::minimumSubtotal(2000),
                PromotionCondition::currency('USD'),
            ]),
            PromotionAction::fixed(500),
        );

        $result = (new PromotionApplicationService(new PromotionEvaluationService()))
            ->apply($promotion, new PromotionEvaluationRequestDTO(2500, 'usd'));

        self::assertTrue($result->eligible);
        self::assertSame(500, $result->discountAmountMinor);
        self::assertSame(2000, $result->finalAmountMinor);
        self::assertSame(
            ['promotion_active', 'minimum_subtotal_met', 'currency_matched', 'promotion_eligible', 'promotion_applied_fixed'],
            $result->reasons,
        );
    }

    public function testPercentageUsesIntegerBasisPoints(): void
    {
        $promotion = new Promotion(
            'percent',
            'Percent',
            new PromotionRule(),
            PromotionAction::percentage(1250),
        );

        $result = (new PromotionApplicationService(new PromotionEvaluationService()))
            ->apply($promotion, new PromotionEvaluationRequestDTO(999, 'USD'));

        self::assertSame(124, $result->discountAmountMinor);
        self::assertSame(875, $result->finalAmountMinor);
    }

    public function testCurrencyMismatchIsAuditableAndDoesNotApply(): void
    {
        $promotion = new Promotion(
            'eur',
            'EUR',
            new PromotionRule([PromotionCondition::currency('EUR')]),
            PromotionAction::fixed(100),
        );

        $result = (new PromotionApplicationService(new PromotionEvaluationService()))
            ->apply($promotion, new PromotionEvaluationRequestDTO(1000, 'USD'));

        self::assertFalse($result->eligible);
        self::assertSame(0, $result->discountAmountMinor);
        self::assertSame(['promotion_active', 'currency_mismatch', 'promotion_not_applied'], $result->reasons);
    }

    public function testPromotionConditionsRejectInvalidDefinitions(): void
    {
        try {
            PromotionCondition::minimumSubtotal(-1);
            self::fail('Negative minimum subtotal must fail.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Minimum subtotal cannot be negative.', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        PromotionCondition::currency('US1');
    }

    public function testPromotionWindowRequiresExplicitTimeContext(): void
    {
        $promotion = new Promotion(
            'window',
            'Window',
            new PromotionRule(),
            PromotionAction::fixed(100),
            startsAt: new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            endsAt: new \DateTimeImmutable('2026-09-30T23:59:59+00:00'),
        );

        $result = (new PromotionApplicationService(new PromotionEvaluationService()))
            ->apply($promotion, new PromotionEvaluationRequestDTO(1000, 'USD'));

        self::assertFalse($result->eligible);
        self::assertSame(['promotion_active', 'promotion_time_context_missing', 'promotion_not_applied'], $result->reasons);
    }

    public function testPromotionWindowEvaluatesAgainstCallerSuppliedTime(): void
    {
        $promotion = new Promotion(
            'window',
            'Window',
            new PromotionRule(),
            PromotionAction::fixed(100),
            startsAt: new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            endsAt: new \DateTimeImmutable('2026-09-30T23:59:59+00:00'),
        );

        $result = (new PromotionApplicationService(new PromotionEvaluationService()))
            ->apply(
                $promotion,
                new PromotionEvaluationRequestDTO(
                    1000,
                    'USD',
                    new \DateTimeImmutable('2026-09-20T12:00:00+00:00'),
                ),
            );

        self::assertTrue($result->eligible);
        self::assertSame(
            [
                'promotion_active',
                'promotion_start_window_met',
                'promotion_end_window_met',
                'promotion_eligible',
                'promotion_applied_fixed',
            ],
            $result->reasons,
        );
    }
}
