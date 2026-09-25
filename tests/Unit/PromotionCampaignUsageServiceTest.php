<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\Enum\PromotionCampaignStatus;
use App\Promoting\Service\PromotionCampaignUsageService;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignUsage;
use App\Promoting\ValueObject\PromotionCampaignUsageLedger;
use PHPUnit\Framework\TestCase;

/** Verifies global campaign application limits, replay, and reversal slot release. */
final class PromotionCampaignUsageServiceTest extends TestCase
{
    private function campaign(?int $applicationLimit = null): PromotionCampaign
    {
        return new PromotionCampaign(
            'campaign',
            'Campaign',
            ['promo'],
            status: PromotionCampaignStatus::Active,
            applicationLimit: $applicationLimit,
        );
    }

    public function testUnlimitedCampaignCanRecordUsage(): void
    {
        $service = new PromotionCampaignUsageService();
        $ledger = new PromotionCampaignUsageLedger();

        $validation = $service->validate($this->campaign(), $ledger, 'order-1');
        self::assertTrue($validation->allowed);
        self::assertSame(['campaign_usage_unlimited'], $validation->reasons);

        $result = $service->record($this->campaign(), $ledger, 'order-1');
        self::assertTrue($result->changed);
        self::assertNotNull($result->usage);
        self::assertSame(1, $result->ledger->activeCount('campaign'));
        self::assertContains('campaign_usage_recorded', $result->reasons);
    }

    public function testApplicationLimitRejectsNewOrderAfterCapacityIsConsumed(): void
    {
        $service = new PromotionCampaignUsageService();
        $campaign = $this->campaign(1);
        $ledger = new PromotionCampaignUsageLedger([
            new PromotionCampaignUsage('campaign', 'order-1'),
        ]);

        $validation = $service->validate($campaign, $ledger, 'order-2');
        self::assertFalse($validation->allowed);
        self::assertSame(['campaign_application_limit_reached'], $validation->reasons);

        $result = $service->record($campaign, $ledger, 'order-2');
        self::assertFalse($result->changed);
        self::assertSame($ledger, $result->ledger);
        self::assertNull($result->usage);
        self::assertSame(
            ['campaign_application_limit_reached', 'campaign_usage_not_recorded'],
            $result->reasons,
        );
    }

    public function testSameOrderReplayIsAllowedAtApplicationLimitWithoutDuplicateUsage(): void
    {
        $service = new PromotionCampaignUsageService();
        $campaign = $this->campaign(1);
        $usage = new PromotionCampaignUsage('campaign', 'order-1');
        $ledger = new PromotionCampaignUsageLedger([$usage]);

        $validation = $service->validate($campaign, $ledger, 'order-1');
        self::assertTrue($validation->allowed);
        self::assertSame(['campaign_usage_idempotent_replay'], $validation->reasons);

        $result = $service->record($campaign, $ledger, 'order-1');
        self::assertFalse($result->changed);
        self::assertSame($ledger, $result->ledger);
        self::assertSame($usage, $result->usage);
        self::assertSame(1, $result->ledger->activeCount('campaign'));
    }

    public function testReversalReleasesApplicationSlotAndIsIdempotent(): void
    {
        $service = new PromotionCampaignUsageService();
        $campaign = $this->campaign(1);
        $ledger = new PromotionCampaignUsageLedger([
            new PromotionCampaignUsage('campaign', 'order-1'),
        ]);

        $reversal = $service->reverse($campaign, $ledger, 'order-1');
        self::assertTrue($reversal->changed);
        self::assertNotNull($reversal->usage);
        self::assertSame(0, $reversal->ledger->activeCount('campaign'));
        self::assertSame(['campaign_usage_reversed'], $reversal->reasons);

        $replay = $service->reverse($campaign, $reversal->ledger, 'order-1');
        self::assertFalse($replay->changed);
        self::assertSame($reversal->ledger, $replay->ledger);
        self::assertNull($replay->usage);
        self::assertSame(['campaign_usage_reversal_idempotent_noop'], $replay->reasons);

        $recorded = $service->record($campaign, $reversal->ledger, 'order-2');
        self::assertTrue($recorded->changed);
        self::assertSame(1, $recorded->ledger->activeCount('campaign'));
    }

    public function testUsageLedgerRecordAndReverseAreIdempotent(): void
    {
        $usage = new PromotionCampaignUsage('campaign', 'order-1');
        $ledger = new PromotionCampaignUsageLedger();

        self::assertSame('campaign|order-1', $usage->key());
        $recorded = $ledger->record($usage);
        self::assertSame($usage, $recorded->findActive('campaign', 'order-1'));
        self::assertSame($recorded, $recorded->record($usage));
        self::assertSame(1, $recorded->activeCount('campaign'));

        $reversed = $recorded->reverse('campaign', 'order-1');
        self::assertNull($reversed->findActive('campaign', 'order-1'));
        self::assertSame(0, $reversed->activeCount('campaign'));
        self::assertSame($reversed, $reversed->reverse('campaign', 'order-1'));
    }

    public function testInvalidCampaignUsageInputsAreRejected(): void
    {
        try {
            new PromotionCampaignUsage('', 'order-1');
            self::fail('Empty campaign id must fail.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Campaign id cannot be empty for usage.', $exception->getMessage());
        }

        try {
            new PromotionCampaignUsage('campaign', '');
            self::fail('Empty order id must fail.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Order id cannot be empty for campaign usage.', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        (new PromotionCampaignUsageService())->validate(
            $this->campaign(),
            new PromotionCampaignUsageLedger(),
            '   ',
        );
    }

    public function testCampaignRejectsInvalidApplicationLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PromotionCampaign(
            'campaign',
            'Campaign',
            ['promo'],
            applicationLimit: 0,
        );
    }
}
