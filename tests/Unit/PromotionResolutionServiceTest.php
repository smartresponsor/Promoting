<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Enum\PromotionStackingMode;
use App\Promoting\Service\PromotionApplicationService;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\Service\PromotionResolutionService;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Verifies deterministic priority, stacking, and exclusivity semantics. */
final class PromotionResolutionServiceTest extends TestCase
{
    public function testHigherPriorityAppliesFirstAndStackingUsesRemainingSubtotal(): void
    {
        $service = new PromotionResolutionService(
            new PromotionApplicationService(new PromotionEvaluationService()),
        );

        $low = new Promotion(
            'b-low',
            'Low',
            new PromotionRule(),
            PromotionAction::percentage(1000),
            priority: 10,
        );
        $high = new Promotion(
            'a-high',
            'High',
            new PromotionRule(),
            PromotionAction::fixed(200),
            priority: 20,
        );

        $result = $service->resolve([$low, $high], new PromotionEvaluationRequestDTO(1000, 'USD'));

        self::assertSame(280, $result->totalDiscountAmountMinor);
        self::assertSame(720, $result->finalAmountMinor);
        self::assertSame(['a-high', 'b-low'], array_map(
            static fn ($application): string => $application->promotionId,
            $result->applications,
        ));
    }

    public function testExclusivePromotionStopsLowerPriorityApplications(): void
    {
        $service = new PromotionResolutionService(
            new PromotionApplicationService(new PromotionEvaluationService()),
        );

        $exclusive = new Promotion(
            'exclusive',
            'Exclusive',
            new PromotionRule(),
            PromotionAction::fixed(300),
            priority: 100,
            stackingMode: PromotionStackingMode::Exclusive,
        );
        $later = new Promotion(
            'later',
            'Later',
            new PromotionRule(),
            PromotionAction::fixed(200),
            priority: 1,
        );

        $result = $service->resolve([$later, $exclusive], new PromotionEvaluationRequestDTO(1000, 'USD'));

        self::assertSame(300, $result->totalDiscountAmountMinor);
        self::assertSame(700, $result->finalAmountMinor);
        self::assertCount(1, $result->applications);
        self::assertSame('exclusive', $result->applications[0]->promotionId);
    }

    public function testEqualPriorityUsesPromotionIdAsStableTieBreaker(): void
    {
        $service = new PromotionResolutionService(
            new PromotionApplicationService(new PromotionEvaluationService()),
        );

        $zulu = new Promotion('zulu', 'Zulu', new PromotionRule(), PromotionAction::fixed(100), priority: 5);
        $alpha = new Promotion('alpha', 'Alpha', new PromotionRule(), PromotionAction::fixed(100), priority: 5);

        $result = $service->resolve([$zulu, $alpha], new PromotionEvaluationRequestDTO(500, 'USD'));

        self::assertSame(['alpha', 'zulu'], array_map(
            static fn ($application): string => $application->promotionId,
            $result->applications,
        ));
    }
}
