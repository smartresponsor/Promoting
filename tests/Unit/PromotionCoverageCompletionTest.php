<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
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
use App\Promoting\ValueObject\PromotionCondition;
use App\Promoting\ValueObject\PromotionRedemption;
use App\Promoting\ValueObject\PromotionRedemptionLedger;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Completes high-value branch paths left by the feature-focused test suite. */
final class PromotionCoverageCompletionTest extends TestCase
{
    public function testResolutionStopsWhenFirstPromotionConsumesEntireSubtotal(): void
    {
        $evaluation = new PromotionEvaluationService();
        $service = new PromotionResolutionService(new PromotionApplicationService($evaluation));
        $first = new Promotion('first', 'First', new PromotionRule(), PromotionAction::fixed(1000), priority: 10);
        $second = new Promotion('second', 'Second', new PromotionRule(), PromotionAction::fixed(100), priority: 1);

        $result = $service->resolve([$second, $first], new PromotionEvaluationRequestDTO(500, 'USD'));

        self::assertSame(0, $result->finalAmountMinor);
        self::assertCount(1, $result->applications);
    }

    public function testCampaignApplicationHandlesAvailableCampaignWithoutEligibleMembers(): void
    {
        $evaluation = new PromotionEvaluationService();
        $campaignService = new PromotionCampaignService();
        $service = new PromotionCampaignApplicationService(
            new PromotionCampaignSelectionService(
                $campaignService,
                new PromotionSelectionService($evaluation),
            ),
            new PromotionResolutionService(new PromotionApplicationService($evaluation)),
            $campaignService,
        );
        $inactive = new Promotion(
            'inactive',
            'Inactive',
            new PromotionRule(),
            PromotionAction::fixed(100),
            status: \App\Promoting\Enum\PromotionStatus::Inactive,
        );
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['inactive'],
            status: PromotionCampaignStatus::Active,
        );

        $result = $service->apply(
            $campaign,
            new PromotionCatalog([$inactive]),
            new PromotionEvaluationRequestDTO(
                1000,
                'USD',
                new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
            ),
        );

        self::assertFalse($result->applied);
        self::assertContains('campaign_application_no_eligible_promotions', $result->reasons);
    }

    public function testCatalogAddCreatesNewImmutableCatalog(): void
    {
        $first = new Promotion('first', 'First', new PromotionRule(), PromotionAction::fixed(1));
        $second = new Promotion('second', 'Second', new PromotionRule(), PromotionAction::fixed(1));
        $catalog = new PromotionCatalog([$first]);

        $updated = $catalog->add($second);

        self::assertCount(1, $catalog->promotions);
        self::assertCount(2, $updated->promotions);
        self::assertSame($second, $updated->find('second'));
    }

    public function testPromotionSupportsOpenEndedStartWindowAndStatusCopy(): void
    {
        $promotion = new Promotion(
            'open',
            'Open',
            new PromotionRule(),
            PromotionAction::fixed(1),
            startsAt: new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
        );

        self::assertTrue(
            (new PromotionEvaluationService())->evaluate(
                $promotion,
                new PromotionEvaluationRequestDTO(
                    100,
                    'USD',
                    new \DateTimeImmutable('2026-09-22T00:00:00+00:00'),
                ),
            )->eligible,
        );
        self::assertSame($promotion->startsAt, $promotion->withStatus($promotion->status)->startsAt);
    }

    public function testLedgerTraversesUnrelatedEntriesBeforeAndAfterTarget(): void
    {
        $unrelated = new PromotionRedemption('other', 'other-customer', 'other-order');
        $target = new PromotionRedemption('code', 'customer', 'order');
        $after = new PromotionRedemption('after', 'after-customer', 'after-order');
        $ledger = new PromotionRedemptionLedger([$unrelated, $target, $after]);

        self::assertSame($target, $ledger->findActive('code', 'customer', 'order'));

        $reversed = $ledger->reverse('code', 'customer', 'order');
        self::assertCount(3, $reversed->entries);
        self::assertNull($reversed->findActive('code', 'customer', 'order'));
        self::assertSame(1, $reversed->redeemedCount('other'));
        self::assertSame(1, $reversed->redeemedCount('after'));
    }

    public function testNormalizedConditionsAndBenefitRequestRemainValid(): void
    {
        self::assertSame('USD', PromotionCondition::currency(' usd ')->expected);
        self::assertSame(0, PromotionCondition::minimumSubtotal(0)->expected);

        $request = new PromotionBenefitRequestDTO(
            0,
            ' usd ',
            ['SKU-A' => 0, 'SKU-B' => 2],
        );

        self::assertSame('USD', $request->currencyCode);
        self::assertSame(2, $request->itemQuantities['SKU-B']);
    }
}
