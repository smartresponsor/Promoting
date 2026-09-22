<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionCampaignApplicationResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCatalog;

/** Applies eligible campaign promotions and records campaign spend only after budget validation. */
interface PromotionCampaignApplicationServiceInterface
{
    /** Applies campaign-scoped promotions atomically with remaining-budget validation. */
    public function apply(
        PromotionCampaign $campaign,
        PromotionCatalog $catalog,
        PromotionEvaluationRequestDTO $request,
    ): PromotionCampaignApplicationResultDTO;
}
