<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\DTO\PromotionSelectionResultDTO;
use App\Promoting\ValueObject\PromotionCatalog;

/** Selects eligible promotions from an explicit catalog without hidden state. */
interface PromotionSelectionServiceInterface
{
    /** Evaluates automatic catalog promotions and returns eligible promotions in deterministic order. */
    public function select(
        PromotionCatalog $catalog,
        PromotionEvaluationRequestDTO $request,
    ): PromotionSelectionResultDTO;

    /** Evaluates automatic and campaign-only catalog promotions inside an available campaign context. */
    public function selectForCampaign(
        PromotionCatalog $catalog,
        PromotionEvaluationRequestDTO $request,
    ): PromotionSelectionResultDTO;
}
