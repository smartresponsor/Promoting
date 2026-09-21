<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\Service\PromotionBenefitService;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionBenefit;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Verifies typed non-price promotion effects without downstream fulfillment side effects. */
final class PromotionBenefitServiceTest extends TestCase
{
    public function testBuyXGetYProducesDeterministicRewardQuantity(): void
    {
        $promotion = new Promotion(
            'bxgy',
            'Buy two get one',
            new PromotionRule(),
            PromotionAction::fixed(0),
            benefit: PromotionBenefit::buyXGetY('SKU-A', 2, 'SKU-A', 1),
        );

        $result = (new PromotionBenefitService(new PromotionEvaluationService()))->evaluate(
            $promotion,
            new PromotionBenefitRequestDTO(5000, 'usd', ['SKU-A' => 5]),
        );

        self::assertTrue($result->eligible);
        self::assertSame('SKU-A', $result->rewardSku);
        self::assertSame(2, $result->rewardQuantity);
        self::assertFalse($result->freeShipping);
    }

    public function testBuyXGetYDoesNotGrantWhenQuantityIsInsufficient(): void
    {
        $promotion = new Promotion(
            'bxgy',
            'Buy two get one',
            new PromotionRule(),
            PromotionAction::fixed(0),
            benefit: PromotionBenefit::buyXGetY('SKU-A', 2, 'SKU-B', 1),
        );

        $result = (new PromotionBenefitService(new PromotionEvaluationService()))->evaluate(
            $promotion,
            new PromotionBenefitRequestDTO(5000, 'USD', ['SKU-A' => 1]),
        );

        self::assertFalse($result->eligible);
        self::assertSame(0, $result->rewardQuantity);
        self::assertSame(
            ['promotion_active', 'promotion_eligible', 'promotion_buy_x_get_y_quantity_not_met'],
            $result->reasons,
        );
    }

    public function testFreeGiftProducesGiftEntitlement(): void
    {
        $promotion = new Promotion(
            'gift',
            'Gift',
            new PromotionRule(),
            PromotionAction::fixed(0),
            benefit: PromotionBenefit::freeGift('GIFT-1', 2),
        );

        $result = (new PromotionBenefitService(new PromotionEvaluationService()))->evaluate(
            $promotion,
            new PromotionBenefitRequestDTO(1000, 'USD', []),
        );

        self::assertTrue($result->eligible);
        self::assertSame('GIFT-1', $result->rewardSku);
        self::assertSame(2, $result->rewardQuantity);
    }

    public function testFreeShippingReturnsEligibilityWithoutExecutingShipping(): void
    {
        $promotion = new Promotion(
            'ship',
            'Free Shipping',
            new PromotionRule(),
            PromotionAction::fixed(0),
            benefit: PromotionBenefit::freeShipping(),
        );

        $result = (new PromotionBenefitService(new PromotionEvaluationService()))->evaluate(
            $promotion,
            new PromotionBenefitRequestDTO(1000, 'USD', []),
        );

        self::assertTrue($result->eligible);
        self::assertTrue($result->freeShipping);
        self::assertNull($result->rewardSku);
        self::assertSame(0, $result->rewardQuantity);
    }
}
