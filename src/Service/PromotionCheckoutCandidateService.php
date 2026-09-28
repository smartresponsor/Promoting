<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionCheckoutCandidateResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionCampaignSelectionServiceInterface;
use App\Promoting\ServiceInterface\PromotionCouponResolutionServiceInterface;
use App\Promoting\ServiceInterface\PromotionSelectionServiceInterface;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Selects automatic, campaign, and coupon promotion candidates before stacking resolution. */
final readonly class PromotionCheckoutCandidateService
{
    public function __construct(
        private PromotionSelectionServiceInterface $selectionService,
        private PromotionCampaignSelectionServiceInterface $campaignSelectionService,
        private PromotionCouponResolutionServiceInterface $couponResolutionService,
    ) {
    }

    public function select(
        PromotionCatalog $catalog,
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $ledger,
        PromotionEvaluationRequestDTO $request,
        ?string $couponCode = null,
        ?string $customerId = null,
        ?PromotionCampaign $campaign = null,
    ): PromotionCheckoutCandidateResultDTO {
        $selection = $this->selectionService->select($catalog, $request);
        $promotions = $selection->promotions;
        $reasons = ['automatic_promotions_selected:'.count($promotions)];

        $campaignSelection = null;
        if (null !== $campaign) {
            $campaignSelection = $this->campaignSelectionService->select($campaign, $catalog, $request);
            $reasons = [...$reasons, ...$campaignSelection->reasons];
            if ($campaignSelection->available) {
                foreach ($campaignSelection->promotions as $promotion) {
                    $promotions = $this->appendUnique($promotions, $promotion);
                }
                $reasons[] = 'campaign_promotions_included:'.count($campaignSelection->promotions);
            } else {
                $reasons[] = 'campaign_promotions_not_included';
            }
        }

        $couponResolution = null;
        if (null !== $couponCode) {
            $couponCode = trim($couponCode);
            if ('' === $couponCode) {
                throw new \InvalidArgumentException('Coupon code cannot be empty when provided.');
            }
            if (null === $customerId || '' === trim($customerId)) {
                throw new \InvalidArgumentException('Customer id is required when a coupon code is provided.');
            }

            $couponResolution = $this->couponResolutionService->resolve(
                $couponBook,
                $catalog,
                $ledger,
                $couponCode,
                $customerId,
                $request,
            );
            $reasons = [...$reasons, ...$couponResolution->reasons];
            if ($couponResolution->eligible && null !== $couponResolution->promotion) {
                $promotions = $this->appendUnique($promotions, $couponResolution->promotion);
                $reasons[] = 'coupon_promotion_included';
            } else {
                $reasons[] = 'coupon_promotion_not_included';
            }
        }

        return new PromotionCheckoutCandidateResultDTO(
            $promotions,
            $couponResolution,
            $campaignSelection,
            $reasons,
        );
    }

    /**
     * @param list<Promotion> $promotions
     *
     * @return list<Promotion>
     */
    private function appendUnique(array $promotions, Promotion $candidate): array
    {
        foreach ($promotions as $promotion) {
            if ($promotion->id === $candidate->id) {
                return $promotions;
            }
        }

        return [...$promotions, $candidate];
    }
}
