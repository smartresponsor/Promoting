<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionCampaignSelectionResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCatalog;

/** Selects eligible promotions belonging to one available campaign. */
interface PromotionCampaignSelectionServiceInterface
{
    /** Resolves campaign members and returns deterministic eligible promotion selection. */
    public function select(
        PromotionCampaign $campaign,
        PromotionCatalog $catalog,
        PromotionEvaluationRequestDTO $request,
    ): PromotionCampaignSelectionResultDTO;
}
