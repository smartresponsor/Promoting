<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionCampaignTransactionResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionCampaignApplicationServiceInterface;
use App\Promoting\ServiceInterface\PromotionCampaignServiceInterface;
use App\Promoting\ServiceInterface\PromotionCampaignTransactionServiceInterface;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignSpend;
use App\Promoting\ValueObject\PromotionCampaignSpendLedger;
use App\Promoting\ValueObject\PromotionCatalog;

/** Keeps campaign aggregate spend and per-order spend ledger synchronized across apply and reversal flows. */
final readonly class PromotionCampaignTransactionService implements PromotionCampaignTransactionServiceInterface
{
    public function __construct(
        private PromotionCampaignApplicationServiceInterface $applicationService,
        private PromotionCampaignServiceInterface $campaignService,
    ) {
    }

    /** Applies one campaign-scoped checkout order and records its spend exactly once. */
    public function apply(
        PromotionCampaign $campaign,
        PromotionCatalog $catalog,
        PromotionCampaignSpendLedger $ledger,
        string $orderId,
        PromotionEvaluationRequestDTO $request,
    ): PromotionCampaignTransactionResultDTO {
        $this->assertOrderId($orderId);
        $this->assertSynchronized($campaign, $ledger);

        $existing = $ledger->findActive($campaign->id, $orderId);
        if (null !== $existing) {
            return new PromotionCampaignTransactionResultDTO(
                true,
                $campaign,
                $ledger,
                null,
                $existing,
                ['campaign_spend_idempotent_replay'],
            );
        }

        $application = $this->applicationService->apply($campaign, $catalog, $request);
        if (!$application->applied || null === $application->resolution) {
            return new PromotionCampaignTransactionResultDTO(
                false,
                $campaign,
                $ledger,
                $application,
                null,
                [...$application->reasons, 'campaign_transaction_not_applied'],
            );
        }

        $amountMinor = $application->resolution->totalDiscountAmountMinor;
        $spend = new PromotionCampaignSpend($campaign->id, $orderId, $amountMinor);

        return new PromotionCampaignTransactionResultDTO(
            true,
            $application->campaign,
            $ledger->record($spend),
            $application,
            $spend,
            [...$application->reasons, 'campaign_spend_recorded'],
        );
    }

    /** Reverses one active campaign order spend idempotently and releases aggregate budget. */
    public function reverse(
        PromotionCampaign $campaign,
        PromotionCampaignSpendLedger $ledger,
        string $orderId,
    ): PromotionCampaignTransactionResultDTO {
        $this->assertOrderId($orderId);
        $this->assertSynchronized($campaign, $ledger);

        $existing = $ledger->findActive($campaign->id, $orderId);
        if (null === $existing) {
            return new PromotionCampaignTransactionResultDTO(
                true,
                $campaign,
                $ledger,
                null,
                null,
                ['campaign_spend_reversal_idempotent_noop'],
            );
        }

        $updatedCampaign = $this->campaignService->releaseSpend($campaign, $existing->amountMinor);

        return new PromotionCampaignTransactionResultDTO(
            true,
            $updatedCampaign,
            $ledger->reverse($campaign->id, $orderId),
            null,
            $existing->reversed(),
            ['campaign_spend_reversed'],
        );
    }

    private function assertOrderId(string $orderId): void
    {
        if ('' === trim($orderId)) {
            throw new \InvalidArgumentException('Order id cannot be empty for campaign transaction.');
        }
    }

    private function assertSynchronized(
        PromotionCampaign $campaign,
        PromotionCampaignSpendLedger $ledger,
    ): void {
        if ($campaign->spentMinor !== $ledger->activeSpendForCampaign($campaign->id)) {
            throw new \DomainException('Campaign spend aggregate does not match campaign spend ledger.');
        }
    }
}
