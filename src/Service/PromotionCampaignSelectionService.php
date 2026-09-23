<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionCampaignSelectionResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionCampaignSelectionServiceInterface;
use App\Promoting\ServiceInterface\PromotionCampaignServiceInterface;
use App\Promoting\ServiceInterface\PromotionSelectionServiceInterface;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCatalog;

/** Resolves campaign membership and delegates promotion eligibility selection deterministically. */
final readonly class PromotionCampaignSelectionService implements PromotionCampaignSelectionServiceInterface
{
    public function __construct(
        private PromotionCampaignServiceInterface $campaignService,
        private PromotionSelectionServiceInterface $selectionService,
    ) {
    }

    /** Selects eligible catalog promotions belonging to an available campaign. */
    public function select(
        PromotionCampaign $campaign,
        PromotionCatalog $catalog,
        PromotionEvaluationRequestDTO $request,
    ): PromotionCampaignSelectionResultDTO {
        if (null === $request->at) {
            return new PromotionCampaignSelectionResultDTO(
                false,
                [],
                [],
                ['campaign_time_context_missing'],
            );
        }

        $campaignEvaluation = $this->campaignService->evaluate($campaign, $request->at);
        if (!$campaignEvaluation->available) {
            return new PromotionCampaignSelectionResultDTO(
                false,
                [],
                [],
                $campaignEvaluation->reasons,
            );
        }

        $members = [];
        $reasons = $campaignEvaluation->reasons;
        foreach ($campaign->promotionIds as $promotionId) {
            $promotion = $catalog->find($promotionId);
            if (null === $promotion) {
                $reasons[] = 'campaign_promotion_missing:'.$promotionId;
                continue;
            }

            $members[] = $promotion;
        }

        if ([] === $members) {
            return new PromotionCampaignSelectionResultDTO(
                true,
                [],
                [],
                [...$reasons, 'campaign_no_resolved_promotions'],
            );
        }

        $selection = $this->selectionService->selectForCampaign(new PromotionCatalog($members), $request);

        return new PromotionCampaignSelectionResultDTO(
            true,
            $selection->promotions,
            $selection->evaluations,
            [...$reasons, 'campaign_promotions_selected'],
        );
    }
}
