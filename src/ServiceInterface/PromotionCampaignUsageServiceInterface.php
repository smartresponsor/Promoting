<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionCampaignUsageMutationResultDTO;
use App\Promoting\DTO\PromotionCampaignUsageValidationDTO;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignUsageLedger;

/** Enforces global campaign application limits over explicit immutable usage history. */
interface PromotionCampaignUsageServiceInterface
{
    /** Validates whether one order may consume a campaign application slot. */
    public function validate(
        PromotionCampaign $campaign,
        PromotionCampaignUsageLedger $ledger,
        string $orderId,
    ): PromotionCampaignUsageValidationDTO;

    /** Records one campaign application idempotently when the global limit permits it. */
    public function record(
        PromotionCampaign $campaign,
        PromotionCampaignUsageLedger $ledger,
        string $orderId,
    ): PromotionCampaignUsageMutationResultDTO;

    /** Reverses one campaign application idempotently and releases its usage slot. */
    public function reverse(
        PromotionCampaign $campaign,
        PromotionCampaignUsageLedger $ledger,
        string $orderId,
    ): PromotionCampaignUsageMutationResultDTO;
}
