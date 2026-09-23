<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\Enum\PromotionCampaignSpendStatus;
use App\Promoting\ValueObject\PromotionCampaignSpend;
use App\Promoting\ValueObject\PromotionCampaignSpendLedger;
use PHPUnit\Framework\TestCase;

/** Verifies campaign spend identity, active totals, idempotent record, and reversal history. */
final class PromotionCampaignSpendLedgerTest extends TestCase
{
    public function testSpendIdentityAndReversalAreStable(): void
    {
        $spend = new PromotionCampaignSpend('campaign', 'order-1', 250);

        self::assertSame('campaign|order-1', $spend->key());
        self::assertSame(PromotionCampaignSpendStatus::Spent, $spend->status);

        $reversed = $spend->reversed();
        self::assertSame(PromotionCampaignSpendStatus::Reversed, $reversed->status);
        self::assertSame(250, $reversed->amountMinor);
        self::assertSame($spend->key(), $reversed->key());
    }

    public function testLedgerRecordsAndReversesSpendIdempotently(): void
    {
        $first = new PromotionCampaignSpend('campaign', 'order-1', 200);
        $second = new PromotionCampaignSpend('campaign', 'order-2', 300);
        $other = new PromotionCampaignSpend('other', 'order-3', 400);

        $ledger = (new PromotionCampaignSpendLedger())
            ->record($first)
            ->record($second)
            ->record($other);

        self::assertSame(500, $ledger->activeSpendForCampaign('campaign'));
        self::assertSame($first, $ledger->findActive('campaign', 'order-1'));
        self::assertNull($ledger->findActive('campaign', 'missing'));
        self::assertSame($ledger, $ledger->record($first));

        $reversed = $ledger->reverse('campaign', 'order-1');
        self::assertSame(300, $reversed->activeSpendForCampaign('campaign'));
        self::assertNull($reversed->findActive('campaign', 'order-1'));
        self::assertSame($reversed, $reversed->reverse('campaign', 'order-1'));
        self::assertSame(400, $reversed->activeSpendForCampaign('other'));
    }

    public function testSpendRejectsInvalidIdentityAndAmount(): void
    {
        try {
            new PromotionCampaignSpend('', 'order', 1);
            self::fail('Empty campaign id must fail.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Campaign id cannot be empty for spend.', $exception->getMessage());
        }

        try {
            new PromotionCampaignSpend('campaign', '', 1);
            self::fail('Empty order id must fail.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Order id cannot be empty for campaign spend.', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        new PromotionCampaignSpend('campaign', 'order', 0);
    }
}
