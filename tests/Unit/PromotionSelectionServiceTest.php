<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Enum\PromotionStatus;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\Service\PromotionSelectionService;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Verifies promotion catalog uniqueness and deterministic eligible selection. */
final class PromotionSelectionServiceTest extends TestCase
{
    public function testCatalogRejectsDuplicatePromotionIds(): void
    {
        $promotion = new Promotion('dup', 'Duplicate', new PromotionRule(), PromotionAction::fixed(100));

        $this->expectException(\InvalidArgumentException::class);
        new PromotionCatalog([$promotion, $promotion]);
    }

    public function testSelectionFiltersIneligiblePromotionsAndOrdersEligibleOnes(): void
    {
        $service = new PromotionSelectionService(new PromotionEvaluationService());

        $inactive = new Promotion(
            'inactive',
            'Inactive',
            new PromotionRule(),
            PromotionAction::fixed(100),
            PromotionStatus::Inactive,
            priority: 100,
        );
        $later = new Promotion(
            'later',
            'Later',
            new PromotionRule(),
            PromotionAction::fixed(100),
            priority: 5,
        );
        $first = new Promotion(
            'first',
            'First',
            new PromotionRule(),
            PromotionAction::fixed(100),
            priority: 10,
        );

        $result = $service->select(
            new PromotionCatalog([$inactive, $later, $first]),
            new PromotionEvaluationRequestDTO(1000, 'USD'),
        );

        self::assertSame(
            ['first', 'later'],
            array_map(static fn (Promotion $promotion): string => $promotion->id, $result->promotions),
        );
        self::assertCount(3, $result->evaluations);
        self::assertFalse($result->evaluations[0]->eligible);
    }

    public function testSelectionRespectsPromotionWindowUsingExplicitTime(): void
    {
        $service = new PromotionSelectionService(new PromotionEvaluationService());
        $windowed = new Promotion(
            'windowed',
            'Windowed',
            new PromotionRule(),
            PromotionAction::fixed(100),
            startsAt: new \DateTimeImmutable('2026-10-01T00:00:00+00:00'),
            endsAt: new \DateTimeImmutable('2026-10-31T23:59:59+00:00'),
        );

        $before = $service->select(
            new PromotionCatalog([$windowed]),
            new PromotionEvaluationRequestDTO(
                1000,
                'USD',
                new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
            ),
        );
        self::assertSame([], $before->promotions);
        self::assertSame(
            ['promotion_active', 'promotion_not_started'],
            $before->evaluations[0]->reasons,
        );

        $during = $service->select(
            new PromotionCatalog([$windowed]),
            new PromotionEvaluationRequestDTO(
                1000,
                'USD',
                new \DateTimeImmutable('2026-10-15T12:00:00+00:00'),
            ),
        );
        self::assertSame(['windowed'], array_map(
            static fn (Promotion $promotion): string => $promotion->id,
            $during->promotions,
        ));
    }
}
