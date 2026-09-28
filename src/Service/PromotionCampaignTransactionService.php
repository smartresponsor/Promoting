<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionCampaignTransactionResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionCampaignApplicationServiceInterface;
use App\Promoting\ServiceInterface\PromotionCampaignServiceInterface;
use App\Promoting\ServiceInterface\PromotionCampaignTransactionServiceInterface;
use App\Promoting\ServiceInterface\PromotionCampaignUsageServiceInterface;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignSpend;
use App\Promoting\ValueObject\PromotionCampaignSpendLedger;
use App\Promoting\ValueObject\PromotionCampaignUsageLedger;
use App\Promoting\ValueObject\PromotionCatalog;

/** Keeps campaign aggregate spend and per-order spend ledger synchronized across apply and reversal flows. */
final readonly class PromotionCampaignTransactionService implements PromotionCampaignTransactionServiceInterface
{
    public function __construct(
        private PromotionCampaignApplicationServiceInterface $applicationService,
        private PromotionCampaignServiceInterface $campaignService,
        private PromotionCampaignUsageServiceInterface $usageService,
    ) {
    }

    /** Applies one campaign-scoped checkout order and records its spend exactly once. */
    public function apply(
        PromotionCampaign $campaign,
        PromotionCatalog $catalog,
        PromotionCampaignSpendLedger $ledger,
        string $orderId,
        PromotionEvaluationRequestDTO $request,
        ?PromotionCampaignUsageLedger $usageLedger = null,
    ): PromotionCampaignTransactionResultDTO {
        $this->assertOrderId($orderId);
        $this->assertSynchronized($campaign, $ledger);
        $this->assertUsageLedgerAvailable($campaign, $usageLedger);

        $replay = $this->replayResult($campaign, $ledger, $orderId, $usageLedger);
        if (null !== $replay) {
            return $replay;
        }

        $usageRejection = $this->usageRejection($campaign, $ledger, $orderId, $usageLedger);
        if (null !== $usageRejection) {
            return $usageRejection;
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
                $usageLedger,
            );
        }

        $spend = new PromotionCampaignSpend(
            $campaign->id,
            $orderId,
            $application->resolution->totalDiscountAmountMinor,
        );
        $nextUsageLedger = $this->recordUsage($campaign, $usageLedger, $orderId);

        return new PromotionCampaignTransactionResultDTO(
            true,
            $application->campaign,
            $ledger->record($spend),
            $application,
            $spend,
            [...$application->reasons, 'campaign_spend_recorded'],
            $nextUsageLedger,
        );
    }

    private function replayResult(
        PromotionCampaign $campaign,
        PromotionCampaignSpendLedger $ledger,
        string $orderId,
        ?PromotionCampaignUsageLedger $usageLedger,
    ): ?PromotionCampaignTransactionResultDTO {
        $existing = $ledger->findActive($campaign->id, $orderId);
        if (null === $existing) {
            return null;
        }
        if (null !== $usageLedger && null === $usageLedger->findActive($campaign->id, $orderId)) {
            throw new \DomainException('Campaign spend replay is missing its campaign usage record.');
        }

        return new PromotionCampaignTransactionResultDTO(
            true,
            $campaign,
            $ledger,
            null,
            $existing,
            ['campaign_spend_idempotent_replay'],
            $usageLedger,
        );
    }

    private function usageRejection(
        PromotionCampaign $campaign,
        PromotionCampaignSpendLedger $ledger,
        string $orderId,
        ?PromotionCampaignUsageLedger $usageLedger,
    ): ?PromotionCampaignTransactionResultDTO {
        if (null === $usageLedger) {
            return null;
        }

        $validation = $this->usageService->validate($campaign, $usageLedger, $orderId);
        if ($validation->allowed) {
            return null;
        }

        return new PromotionCampaignTransactionResultDTO(
            false,
            $campaign,
            $ledger,
            null,
            null,
            [...$validation->reasons, 'campaign_transaction_not_applied'],
            $usageLedger,
        );
    }

    private function recordUsage(
        PromotionCampaign $campaign,
        ?PromotionCampaignUsageLedger $usageLedger,
        string $orderId,
    ): ?PromotionCampaignUsageLedger {
        if (null === $usageLedger) {
            return null;
        }

        $mutation = $this->usageService->record($campaign, $usageLedger, $orderId);
        if (!$mutation->changed && null === $mutation->usage) {
            throw new \DomainException('Campaign usage changed between validation and recording.');
        }

        return $mutation->ledger;
    }

    /** Reverses one active campaign order spend idempotently and releases aggregate budget. */
    public function reverse(
        PromotionCampaign $campaign,
        PromotionCampaignSpendLedger $ledger,
        string $orderId,
        ?PromotionCampaignUsageLedger $usageLedger = null,
    ): PromotionCampaignTransactionResultDTO {
        $this->assertOrderId($orderId);
        $this->assertSynchronized($campaign, $ledger);
        $this->assertUsageLedgerAvailable($campaign, $usageLedger);

        $existing = $ledger->findActive($campaign->id, $orderId);
        if (null === $existing) {
            return new PromotionCampaignTransactionResultDTO(
                true,
                $campaign,
                $ledger,
                null,
                null,
                ['campaign_spend_reversal_idempotent_noop'],
                $usageLedger,
            );
        }

        $updatedCampaign = $this->campaignService->releaseSpend($campaign, $existing->amountMinor);
        $nextUsageLedger = $usageLedger;
        if (null !== $usageLedger) {
            $nextUsageLedger = $this->usageService->reverse($campaign, $usageLedger, $orderId)->ledger;
        }

        return new PromotionCampaignTransactionResultDTO(
            true,
            $updatedCampaign,
            $ledger->reverse($campaign->id, $orderId),
            null,
            $existing->reversed(),
            ['campaign_spend_reversed'],
            $nextUsageLedger,
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

    private function assertUsageLedgerAvailable(
        PromotionCampaign $campaign,
        ?PromotionCampaignUsageLedger $usageLedger,
    ): void {
        if (null !== $campaign->applicationLimit && null === $usageLedger) {
            throw new \DomainException('Campaign application limit requires an explicit campaign usage ledger.');
        }
    }
}
