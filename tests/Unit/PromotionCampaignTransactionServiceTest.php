<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Enum\PromotionCampaignStatus;
use App\Promoting\Service\PromotionApplicationService;
use App\Promoting\Service\PromotionCampaignApplicationService;
use App\Promoting\Service\PromotionCampaignSelectionService;
use App\Promoting\Service\PromotionCampaignService;
use App\Promoting\Service\PromotionCampaignTransactionService;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\Service\PromotionResolutionService;
use App\Promoting\Service\PromotionSelectionService;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignSpend;
use App\Promoting\ValueObject\PromotionCampaignSpendLedger;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Verifies synchronized campaign spend apply, replay, reversal, and consistency guards. */
final class PromotionCampaignTransactionServiceTest extends TestCase
{
    private function service(): PromotionCampaignTransactionService
    {
        $evaluation = new PromotionEvaluationService();
        $campaignService = new PromotionCampaignService();

        return new PromotionCampaignTransactionService(
            new PromotionCampaignApplicationService(
                new PromotionCampaignSelectionService(
                    $campaignService,
                    new PromotionSelectionService($evaluation),
                ),
                new PromotionResolutionService(new PromotionApplicationService($evaluation)),
                $campaignService,
            ),
            $campaignService,
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

    private function campaign(int $spentMinor = 0): PromotionCampaign
    {
        return new PromotionCampaign(
            'campaign',
            'Campaign',
            ['promo'],
            budgetMinor: 1000,
            spentMinor: $spentMinor,
            status: PromotionCampaignStatus::Active,
        );
    }

    private function catalog(): PromotionCatalog
    {
        return new PromotionCatalog([
            new Promotion(
                'promo',
                'Promo',
                new PromotionRule(),
                PromotionAction::fixed(200),
            ),
        ]);
    }

    public function testApplyRecordsSpendAndReplayIsIdempotent(): void
    {
        $first = $this->service()->apply(
            $this->campaign(),
            $this->catalog(),
            new PromotionCampaignSpendLedger(),
            'order-1',
            $this->request(),
        );

        self::assertTrue($first->successful);
        self::assertSame(200, $first->campaign->spentMinor);
        self::assertSame(200, $first->ledger->activeSpendForCampaign('campaign'));
        self::assertNotNull($first->application);
        self::assertNotNull($first->spend);
        self::assertSame(['campaign_spend_recorded'], array_slice($first->reasons, -1));

        $replay = $this->service()->apply(
            $first->campaign,
            $this->catalog(),
            $first->ledger,
            'order-1',
            $this->request(),
        );

        self::assertTrue($replay->successful);
        self::assertSame($first->campaign, $replay->campaign);
        self::assertSame($first->ledger, $replay->ledger);
        self::assertNull($replay->application);
        self::assertSame(['campaign_spend_idempotent_replay'], $replay->reasons);
    }

    public function testReverseReleasesCampaignSpendAndReplayIsNoop(): void
    {
        $applied = $this->service()->apply(
            $this->campaign(),
            $this->catalog(),
            new PromotionCampaignSpendLedger(),
            'order-1',
            $this->request(),
        );

        $reversed = $this->service()->reverse(
            $applied->campaign,
            $applied->ledger,
            'order-1',
        );

        self::assertTrue($reversed->successful);
        self::assertSame(0, $reversed->campaign->spentMinor);
        self::assertSame(0, $reversed->ledger->activeSpendForCampaign('campaign'));
        self::assertNotNull($reversed->spend);
        self::assertSame(['campaign_spend_reversed'], $reversed->reasons);

        $replay = $this->service()->reverse(
            $reversed->campaign,
            $reversed->ledger,
            'order-1',
        );

        self::assertTrue($replay->successful);
        self::assertSame($reversed->campaign, $replay->campaign);
        self::assertSame($reversed->ledger, $replay->ledger);
        self::assertNull($replay->spend);
        self::assertSame(['campaign_spend_reversal_idempotent_noop'], $replay->reasons);
    }

    public function testFailedCampaignApplicationLeavesLedgerUnchanged(): void
    {
        $inactive = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['promo'],
            budgetMinor: 1000,
        );
        $ledger = new PromotionCampaignSpendLedger();

        $result = $this->service()->apply(
            $inactive,
            $this->catalog(),
            $ledger,
            'order-1',
            $this->request(),
        );

        self::assertFalse($result->successful);
        self::assertSame($inactive, $result->campaign);
        self::assertSame($ledger, $result->ledger);
        self::assertNull($result->spend);
        self::assertContains('campaign_transaction_not_applied', $result->reasons);
    }

    public function testTransactionRejectsOutOfSyncAggregateAndLedger(): void
    {
        $ledger = new PromotionCampaignSpendLedger([
            new PromotionCampaignSpend('campaign', 'order-existing', 100),
        ]);

        $this->expectException(\DomainException::class);
        $this->service()->apply(
            $this->campaign(),
            $this->catalog(),
            $ledger,
            'order-1',
            $this->request(),
        );
    }

    public function testTransactionRejectsBlankOrderIdentity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->apply(
            $this->campaign(),
            $this->catalog(),
            new PromotionCampaignSpendLedger(),
            '   ',
            $this->request(),
        );
    }
}
