<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Enum\PromotionCampaignStatus;
use App\Promoting\Service\PromotionApplicationService;
use App\Promoting\Service\PromotionCampaignApplicationService;
use App\Promoting\Service\PromotionCampaignSelectionService;
use App\Promoting\Service\PromotionCampaignService;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\Service\PromotionResolutionService;
use App\Promoting\Service\PromotionSelectionService;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Verifies campaign promotion application and atomic budget spend accounting. */
final class PromotionCampaignApplicationServiceTest extends TestCase
{
    private function service(): PromotionCampaignApplicationService
    {
        $evaluation = new PromotionEvaluationService();
        $campaign = new PromotionCampaignService();

        return new PromotionCampaignApplicationService(
            new PromotionCampaignSelectionService(
                $campaign,
                new PromotionSelectionService($evaluation),
            ),
            new PromotionResolutionService(new PromotionApplicationService($evaluation)),
            $campaign,
        );
    }

    private function request(): PromotionEvaluationRequestDTO
    {
        return new PromotionEvaluationRequestDTO(
            1000,
            'USD',
            new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
        );
    }

    public function testCampaignApplicationRecordsDiscountAsSpend(): void
    {
        $promotion = new Promotion(
            'promo-1',
            'Promo',
            new PromotionRule(),
            PromotionAction::fixed(200),
        );
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['promo-1'],
            budgetMinor: 1000,
            status: PromotionCampaignStatus::Active,
        );

        $result = $this->service()->apply(
            $campaign,
            new PromotionCatalog([$promotion]),
            $this->request(),
        );

        self::assertTrue($result->applied);
        self::assertNotNull($result->resolution);
        self::assertSame(200, $result->resolution->totalDiscountAmountMinor);
        self::assertSame(200, $result->campaign->spentMinor);
        self::assertContains('campaign_application_applied', $result->reasons);
    }

    public function testCampaignApplicationRejectsBudgetOverflowWithoutChangingSpend(): void
    {
        $promotion = new Promotion(
            'promo-1',
            'Promo',
            new PromotionRule(),
            PromotionAction::fixed(200),
        );
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['promo-1'],
            budgetMinor: 250,
            spentMinor: 100,
            status: PromotionCampaignStatus::Active,
        );

        $result = $this->service()->apply(
            $campaign,
            new PromotionCatalog([$promotion]),
            $this->request(),
        );

        self::assertFalse($result->applied);
        self::assertSame($campaign, $result->campaign);
        self::assertSame(100, $result->campaign->spentMinor);
        self::assertContains('campaign_budget_would_exceed', $result->reasons);
    }

    public function testUnavailableCampaignDoesNotResolveOrSpend(): void
    {
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['promo-1'],
            budgetMinor: 1000,
        );

        $result = $this->service()->apply(
            $campaign,
            new PromotionCatalog(),
            $this->request(),
        );

        self::assertFalse($result->applied);
        self::assertNull($result->resolution);
        self::assertSame(0, $result->campaign->spentMinor);
        self::assertSame(
            ['campaign_not_active', 'campaign_application_not_applied'],
            $result->reasons,
        );
    }

    public function testZeroDiscountDoesNotConsumeCampaignBudget(): void
    {
        $promotion = new Promotion(
            'promo-zero',
            'Zero',
            new PromotionRule(),
            PromotionAction::fixed(0),
        );
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['promo-zero'],
            budgetMinor: 1000,
            status: PromotionCampaignStatus::Active,
        );

        $result = $this->service()->apply(
            $campaign,
            new PromotionCatalog([$promotion]),
            $this->request(),
        );

        self::assertFalse($result->applied);
        self::assertNotNull($result->resolution);
        self::assertSame(0, $result->campaign->spentMinor);
        self::assertContains('campaign_application_zero_discount', $result->reasons);
    }
}
