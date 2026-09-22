<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionCampaignApplicationResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionCampaignApplicationServiceInterface;
use App\Promoting\ServiceInterface\PromotionCampaignSelectionServiceInterface;
use App\Promoting\ServiceInterface\PromotionCampaignServiceInterface;
use App\Promoting\ServiceInterface\PromotionResolutionServiceInterface;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCatalog;

/** Applies campaign-scoped promotions atomically with campaign budget accounting. */
final readonly class PromotionCampaignApplicationService implements PromotionCampaignApplicationServiceInterface
{
    public function __construct(
        private PromotionCampaignSelectionServiceInterface $selectionService,
        private PromotionResolutionServiceInterface $resolutionService,
        private PromotionCampaignServiceInterface $campaignService,
    ) {
    }

    /** Applies eligible campaign promotions and records their total discount as campaign spend. */
    public function apply(
        PromotionCampaign $campaign,
        PromotionCatalog $catalog,
        PromotionEvaluationRequestDTO $request,
    ): PromotionCampaignApplicationResultDTO {
        $selection = $this->selectionService->select($campaign, $catalog, $request);
        if (!$selection->available) {
            return new PromotionCampaignApplicationResultDTO(
                false,
                $campaign,
                null,
                [...$selection->reasons, 'campaign_application_not_applied'],
            );
        }

        if ([] === $selection->promotions) {
            return new PromotionCampaignApplicationResultDTO(
                false,
                $campaign,
                null,
                [...$selection->reasons, 'campaign_application_no_eligible_promotions'],
            );
        }

        $resolution = $this->resolutionService->resolve($selection->promotions, $request);
        if ($resolution->totalDiscountAmountMinor < 1) {
            return new PromotionCampaignApplicationResultDTO(
                false,
                $campaign,
                $resolution,
                [...$selection->reasons, 'campaign_application_zero_discount'],
            );
        }

        if (
            null !== $campaign->budgetMinor
            && $campaign->spentMinor + $resolution->totalDiscountAmountMinor > $campaign->budgetMinor
        ) {
            return new PromotionCampaignApplicationResultDTO(
                false,
                $campaign,
                $resolution,
                [...$selection->reasons, 'campaign_budget_would_exceed'],
            );
        }

        $updatedCampaign = $this->campaignService->recordSpend(
            $campaign,
            $resolution->totalDiscountAmountMinor,
        );

        return new PromotionCampaignApplicationResultDTO(
            true,
            $updatedCampaign,
            $resolution,
            [...$selection->reasons, 'campaign_application_applied'],
        );
    }
}
