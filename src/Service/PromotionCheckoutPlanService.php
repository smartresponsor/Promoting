<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionApplicationResultDTO;
use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionBenefitResultDTO;
use App\Promoting\DTO\PromotionCampaignSelectionResultDTO;
use App\Promoting\DTO\PromotionCheckoutPlanResultDTO;
use App\Promoting\DTO\PromotionCouponResolutionResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionBenefitServiceInterface;
use App\Promoting\ServiceInterface\PromotionCampaignSelectionServiceInterface;
use App\Promoting\ServiceInterface\PromotionCheckoutPlanServiceInterface;
use App\Promoting\ServiceInterface\PromotionCouponResolutionServiceInterface;
use App\Promoting\ServiceInterface\PromotionResolutionServiceInterface;
use App\Promoting\ServiceInterface\PromotionSelectionServiceInterface;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Builds a deterministic read-only checkout promotion plan across automatic, coupon, and benefit semantics. */
final readonly class PromotionCheckoutPlanService implements PromotionCheckoutPlanServiceInterface
{
    public function __construct(
        private PromotionSelectionServiceInterface $selectionService,
        private PromotionCampaignSelectionServiceInterface $campaignSelectionService,
        private PromotionCouponResolutionServiceInterface $couponResolutionService,
        private PromotionResolutionServiceInterface $resolutionService,
        private PromotionBenefitServiceInterface $benefitService,
    ) {
    }

    public function plan(
        PromotionCatalog $catalog,
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $ledger,
        PromotionEvaluationRequestDTO $request,
        PromotionBenefitRequestDTO $benefitRequest,
        ?string $couponCode = null,
        ?string $customerId = null,
        ?PromotionCampaign $campaign = null,
    ): PromotionCheckoutPlanResultDTO {
        $this->assertSameContext($request, $benefitRequest);

        $selection = $this->selectionService->select($catalog, $request);
        $candidates = $selection->promotions;
        $reasons = ['automatic_promotions_selected:'.count($candidates)];

        [$campaignSelection, $candidates, $campaignReasons] = $this->includeCampaign(
            $campaign,
            $catalog,
            $request,
            $candidates,
        );
        $reasons = [...$reasons, ...$campaignReasons];

        [$couponResolution, $candidates, $couponReasons] = $this->includeCoupon(
            $couponBook,
            $catalog,
            $ledger,
            $request,
            $couponCode,
            $customerId,
            $candidates,
        );
        $reasons = [...$reasons, ...$couponReasons];

        $resolution = $this->resolutionService->resolve($candidates, $request);
        $benefits = $this->resolveBenefits($candidates, $resolution->applications, $benefitRequest);

        return new PromotionCheckoutPlanResultDTO(
            $resolution,
            $couponResolution,
            $campaignSelection,
            $benefits,
            [...$reasons, 'promotion_plan_resolved'],
        );
    }

    /**
     * @param list<Promotion> $candidates
     *
     * @return array{?PromotionCampaignSelectionResultDTO, list<Promotion>, list<string>}
     */
    private function includeCampaign(
        ?PromotionCampaign $campaign,
        PromotionCatalog $catalog,
        PromotionEvaluationRequestDTO $request,
        array $candidates,
    ): array {
        if (null === $campaign) {
            return [null, $candidates, []];
        }

        $selection = $this->campaignSelectionService->select($campaign, $catalog, $request);
        if (!$selection->available) {
            return [$selection, $candidates, [...$selection->reasons, 'campaign_promotions_not_included']];
        }

        foreach ($selection->promotions as $promotion) {
            $candidates = $this->appendUnique($candidates, $promotion);
        }

        return [
            $selection,
            $candidates,
            [...$selection->reasons, 'campaign_promotions_included:'.count($selection->promotions)],
        ];
    }

    /**
     * @param list<Promotion> $candidates
     *
     * @return array{?PromotionCouponResolutionResultDTO, list<Promotion>, list<string>}
     */
    private function includeCoupon(
        PromotionCouponBook $couponBook,
        PromotionCatalog $catalog,
        PromotionRedemptionLedger $ledger,
        PromotionEvaluationRequestDTO $request,
        ?string $couponCode,
        ?string $customerId,
        array $candidates,
    ): array {
        if (null === $couponCode) {
            return [null, $candidates, []];
        }

        $couponCode = trim($couponCode);
        if ('' === $couponCode) {
            throw new \InvalidArgumentException('Coupon code cannot be empty when provided.');
        }
        if (null === $customerId || '' === trim($customerId)) {
            throw new \InvalidArgumentException('Customer id is required when a coupon code is provided.');
        }

        $resolution = $this->couponResolutionService->resolve(
            $couponBook,
            $catalog,
            $ledger,
            $couponCode,
            $customerId,
            $request,
        );
        if (!$resolution->eligible || null === $resolution->promotion) {
            return [$resolution, $candidates, [...$resolution->reasons, 'coupon_promotion_not_included']];
        }

        return [
            $resolution,
            $this->appendUnique($candidates, $resolution->promotion),
            [...$resolution->reasons, 'coupon_promotion_included'],
        ];
    }

    /**
     * @param list<Promotion>                     $candidates
     * @param list<PromotionApplicationResultDTO> $applications
     *
     * @return list<PromotionBenefitResultDTO>
     */
    private function resolveBenefits(
        array $candidates,
        array $applications,
        PromotionBenefitRequestDTO $benefitRequest,
    ): array {
        $candidateById = [];
        foreach ($candidates as $promotion) {
            $candidateById[$promotion->id] = $promotion;
        }

        $benefits = [];
        foreach ($applications as $application) {
            if (!$application->eligible) {
                continue;
            }

            $promotion = $candidateById[$application->promotionId] ?? null;
            if (null === $promotion || null === $promotion->benefit) {
                continue;
            }

            $benefit = $this->benefitService->evaluate($promotion, $benefitRequest);
            if ($benefit->eligible) {
                $benefits[] = $benefit;
            }
        }

        return $benefits;
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

    private function assertSameContext(
        PromotionEvaluationRequestDTO $request,
        PromotionBenefitRequestDTO $benefitRequest,
    ): void {
        if ($request->subtotalMinor !== $benefitRequest->subtotalMinor) {
            throw new \InvalidArgumentException('Promotion monetary and benefit subtotal contexts must match.');
        }
        if ($request->currencyCode !== $benefitRequest->currencyCode) {
            throw new \InvalidArgumentException('Promotion monetary and benefit currency contexts must match.');
        }
        if ($request->at != $benefitRequest->at) {
            throw new \InvalidArgumentException('Promotion monetary and benefit time contexts must match.');
        }
    }
}
