<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Enum\PromotionActivationMode;
use App\Promoting\Enum\PromotionCampaignStatus;
use App\Promoting\Enum\PromotionStatus;
use App\Promoting\Service\PromotionCampaignSelectionService;
use App\Promoting\Service\PromotionCampaignService;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\Service\PromotionSelectionService;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Verifies campaign availability, membership resolution, and deterministic promotion selection. */
final class PromotionCampaignSelectionServiceTest extends TestCase
{
    private function service(): PromotionCampaignSelectionService
    {
        return new PromotionCampaignSelectionService(
            new PromotionCampaignService(),
            new PromotionSelectionService(new PromotionEvaluationService()),
        );
    }

    public function testCampaignSelectionRequiresExplicitTimeContext(): void
    {
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['promo-1'],
            status: PromotionCampaignStatus::Active,
        );

        $result = $this->service()->select(
            $campaign,
            new PromotionCatalog(),
            new PromotionEvaluationRequestDTO(1000, 'USD'),
        );

        self::assertFalse($result->available);
        self::assertSame(['campaign_time_context_missing'], $result->reasons);
        self::assertSame([], $result->promotions);
    }

    public function testInactiveCampaignDoesNotResolveMembers(): void
    {
        $campaign = new PromotionCampaign('campaign', 'Campaign', ['promo-1']);

        $result = $this->service()->select(
            $campaign,
            new PromotionCatalog(),
            new PromotionEvaluationRequestDTO(
                1000,
                'USD',
                new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
            ),
        );

        self::assertFalse($result->available);
        self::assertSame(['campaign_not_active'], $result->reasons);
        self::assertSame([], $result->evaluations);
    }

    public function testMissingMemberIsReportedWhileResolvedMembersAreSelected(): void
    {
        $high = new Promotion(
            'high',
            'High',
            new PromotionRule(),
            PromotionAction::fixed(100),
            priority: 20,
        );
        $low = new Promotion(
            'low',
            'Low',
            new PromotionRule(),
            PromotionAction::fixed(100),
            priority: 10,
        );
        $inactive = new Promotion(
            'inactive',
            'Inactive',
            new PromotionRule(),
            PromotionAction::fixed(100),
            PromotionStatus::Inactive,
            priority: 100,
        );
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['low', 'missing', 'inactive', 'high'],
            status: PromotionCampaignStatus::Active,
        );

        $result = $this->service()->select(
            $campaign,
            new PromotionCatalog([$low, $inactive, $high]),
            new PromotionEvaluationRequestDTO(
                1000,
                'USD',
                new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
            ),
        );

        self::assertTrue($result->available);
        self::assertSame(
            ['high', 'low'],
            array_map(static fn (Promotion $promotion): string => $promotion->id, $result->promotions),
        );
        self::assertCount(3, $result->evaluations);
        self::assertContains('campaign_promotion_missing:missing', $result->reasons);
        self::assertContains('campaign_promotions_selected', $result->reasons);
    }

    public function testCampaignContextSelectsCampaignOnlyPromotionButStillRejectsCouponOnlyPromotion(): void
    {
        $campaignOnly = new Promotion(
            'campaign-only',
            'Campaign Only',
            new PromotionRule(),
            PromotionAction::fixed(100),
            priority: 20,
            activationMode: PromotionActivationMode::Campaign,
        );
        $automatic = new Promotion(
            'automatic',
            'Automatic',
            new PromotionRule(),
            PromotionAction::fixed(100),
            priority: 10,
        );
        $couponOnly = new Promotion(
            'coupon-only',
            'Coupon Only',
            new PromotionRule(),
            PromotionAction::fixed(100),
            priority: 30,
            activationMode: PromotionActivationMode::Coupon,
        );
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['coupon-only', 'automatic', 'campaign-only'],
            status: PromotionCampaignStatus::Active,
        );

        $result = $this->service()->select(
            $campaign,
            new PromotionCatalog([$couponOnly, $automatic, $campaignOnly]),
            new PromotionEvaluationRequestDTO(
                1000,
                'USD',
                new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
            ),
        );

        self::assertTrue($result->available);
        self::assertSame(
            ['campaign-only', 'automatic'],
            array_map(static fn (Promotion $promotion): string => $promotion->id, $result->promotions),
        );
        self::assertCount(3, $result->evaluations);
        self::assertSame(['promotion_coupon_required'], $result->evaluations[0]->reasons);
    }

    public function testAvailableCampaignWithOnlyMissingMembersReturnsEmptySelection(): void
    {
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['missing'],
            status: PromotionCampaignStatus::Active,
        );

        $result = $this->service()->select(
            $campaign,
            new PromotionCatalog(),
            new PromotionEvaluationRequestDTO(
                1000,
                'USD',
                new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
            ),
        );

        self::assertTrue($result->available);
        self::assertSame([], $result->promotions);
        self::assertContains('campaign_promotion_missing:missing', $result->reasons);
        self::assertContains('campaign_no_resolved_promotions', $result->reasons);
    }
}
