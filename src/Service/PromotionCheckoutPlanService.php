<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionCheckoutPlanResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionCheckoutPlanServiceInterface;
use App\Promoting\ServiceInterface\PromotionResolutionServiceInterface;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Builds a deterministic read-only checkout promotion plan across automatic, coupon, and benefit semantics. */
final readonly class PromotionCheckoutPlanService implements PromotionCheckoutPlanServiceInterface
{
    public function __construct(
        private PromotionCheckoutCandidateService $candidateService,
        private PromotionResolutionServiceInterface $resolutionService,
        private PromotionCheckoutBenefitResolutionService $benefitResolutionService,
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

        $candidateResult = $this->candidateService->select(
            $catalog,
            $couponBook,
            $ledger,
            $request,
            $couponCode,
            $customerId,
            $campaign,
        );
        $resolution = $this->resolutionService->resolve($candidateResult->promotions, $request);
        $benefits = $this->benefitResolutionService->resolve(
            $candidateResult->promotions,
            $resolution->applications,
            $benefitRequest,
        );

        return new PromotionCheckoutPlanResultDTO(
            $resolution,
            $candidateResult->couponResolution,
            $candidateResult->campaignSelection,
            $benefits,
            [...$candidateResult->reasons, 'promotion_plan_resolved'],
        );
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
