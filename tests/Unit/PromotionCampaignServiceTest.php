<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\Enum\PromotionCampaignStatus;
use App\Promoting\Service\PromotionCampaignService;
use App\Promoting\ValueObject\PromotionCampaign;
use PHPUnit\Framework\TestCase;

/** Verifies campaign lifecycle, deterministic windows, and budget controls. */
final class PromotionCampaignServiceTest extends TestCase
{
    public function testActivationPauseResumeAndAvailabilityAreExplicitlyTimed(): void
    {
        $service = new PromotionCampaignService();
        $campaign = new PromotionCampaign(
            'fall-sale',
            'Fall Sale',
            ['promo-1', 'promo-2'],
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-09-30T23:59:59+00:00'),
            budgetMinor: 1000,
        );
        $at = new \DateTimeImmutable('2026-09-20T12:00:00+00:00');

        $active = $service->activate($campaign, $at);
        self::assertSame(PromotionCampaignStatus::Active, $active->status);
        self::assertTrue($service->evaluate($active, $at)->available);

        $paused = $service->pause($active);
        self::assertSame(PromotionCampaignStatus::Paused, $paused->status);
        self::assertFalse($service->evaluate($paused, $at)->available);

        $resumed = $service->resume($paused, $at);
        self::assertSame(PromotionCampaignStatus::Active, $resumed->status);
    }

    public function testActivationRejectsOutsideWindow(): void
    {
        $service = new PromotionCampaignService();
        $campaign = new PromotionCampaign(
            'future',
            'Future',
            ['promo-1'],
            new \DateTimeImmutable('2026-10-01T00:00:00+00:00'),
        );

        $this->expectException(\DomainException::class);
        $service->activate($campaign, new \DateTimeImmutable('2026-09-20T12:00:00+00:00'));
    }

    public function testSpendCannotExceedBudgetAndExhaustionMakesCampaignUnavailable(): void
    {
        $service = new PromotionCampaignService();
        $at = new \DateTimeImmutable('2026-09-20T12:00:00+00:00');
        $campaign = $service->activate(
            new PromotionCampaign('budget', 'Budget', ['promo-1'], budgetMinor: 500),
            $at,
        );

        $spent = $service->recordSpend($campaign, 500);
        self::assertSame(500, $spent->spentMinor);
        self::assertFalse($service->evaluate($spent, $at)->available);
        self::assertSame(
            ['campaign_active', 'campaign_start_window_met', 'campaign_end_window_met', 'campaign_budget_exhausted'],
            $service->evaluate($spent, $at)->reasons,
        );

        $this->expectException(\DomainException::class);
        $service->recordSpend($spent, 1);
    }
}
