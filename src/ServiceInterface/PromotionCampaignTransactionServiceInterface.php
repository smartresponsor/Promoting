<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionCampaignTransactionResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignSpendLedger;
use App\Promoting\ValueObject\PromotionCatalog;

/** Applies and reverses campaign spend by stable order identity over explicit immutable state. */
interface PromotionCampaignTransactionServiceInterface
{
    public function apply(
        PromotionCampaign $campaign,
        PromotionCatalog $catalog,
        PromotionCampaignSpendLedger $ledger,
        string $orderId,
        PromotionEvaluationRequestDTO $request,
    ): PromotionCampaignTransactionResultDTO;

    public function reverse(
        PromotionCampaign $campaign,
        PromotionCampaignSpendLedger $ledger,
        string $orderId,
    ): PromotionCampaignTransactionResultDTO;
}
