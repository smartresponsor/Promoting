<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionCheckoutApplicationResultDTO;
use App\Promoting\DTO\PromotionCheckoutReversalResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionCampaignServiceInterface;
use App\Promoting\ServiceInterface\PromotionCampaignUsageServiceInterface;
use App\Promoting\ServiceInterface\PromotionCheckoutApplicationServiceInterface;
use App\Promoting\ServiceInterface\PromotionCheckoutPlanServiceInterface;
use App\Promoting\ServiceInterface\PromotionCouponServiceInterface;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignSpendLedger;
use App\Promoting\ValueObject\PromotionCampaignUsageLedger;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Applies a unified checkout plan and records only promotion-owned coupon/campaign effects. */
final readonly class PromotionCheckoutApplicationService implements PromotionCheckoutApplicationServiceInterface
{
    private PromotionCheckoutApplicationPlanService $applicationPlanService;
    private PromotionCheckoutCouponOperationService $couponOperationService;
    private PromotionCheckoutCampaignOperationService $campaignOperationService;

    public function __construct(
        PromotionCheckoutPlanServiceInterface $planService,
        PromotionCouponServiceInterface $couponService,
        PromotionCampaignServiceInterface $campaignService,
        PromotionCampaignUsageServiceInterface $usageService,
    ) {
        $this->applicationPlanService = new PromotionCheckoutApplicationPlanService($planService);
        $this->couponOperationService = new PromotionCheckoutCouponOperationService($couponService);
        $this->campaignOperationService = new PromotionCheckoutCampaignOperationService($campaignService, $usageService);
    }

    /** Applies coupon and optional campaign effects only after one unified checkout resolution. */
    public function apply(
        PromotionCatalog $catalog,
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $ledger,
        PromotionEvaluationRequestDTO $request,
        PromotionBenefitRequestDTO $benefitRequest,
        ?string $couponCode = null,
        ?string $customerId = null,
        ?string $orderId = null,
        ?PromotionCampaign $campaign = null,
        ?PromotionCampaignSpendLedger $campaignSpendLedger = null,
        ?PromotionCampaignUsageLedger $campaignUsageLedger = null,
    ): PromotionCheckoutApplicationResultDTO {
        $couponPlanning = $this->couponOperationService->preparePlanning(
            $ledger,
            $couponCode,
            $customerId,
            $orderId,
        );
        $campaignPlanning = $this->campaignOperationService->preparePlanning(
            $campaign,
            $campaignSpendLedger,
            $campaignUsageLedger,
            $orderId,
        );
        $applicationPlan = $this->applicationPlanService->plan(
            $catalog,
            $couponBook,
            $couponPlanning->ledger,
            $request,
            $benefitRequest,
            $couponCode,
            $customerId,
            $campaignPlanning,
        );
        $couponEffect = $this->couponOperationService->apply(
            $ledger,
            $applicationPlan->plan,
            $couponCode,
            $customerId,
            $orderId,
            $request,
            $couponPlanning->replay,
        );
        $campaignEffect = $this->campaignOperationService->apply(
            $campaign,
            $campaignSpendLedger,
            $campaignUsageLedger,
            $campaignPlanning->existingSpend,
            $applicationPlan->plan,
            $applicationPlan->campaignDiscountAmountMinor,
            $applicationPlan->campaignBudgetRejected,
            $campaignPlanning->usageRejected,
            $orderId,
        );

        return new PromotionCheckoutApplicationResultDTO(
            $applicationPlan->plan,
            $couponEffect->ledger,
            $couponEffect->redemption,
            [...$couponEffect->reasons, ...$campaignEffect->reasons],
            $campaignEffect->campaign,
            $campaignEffect->spendLedger,
            $campaignEffect->spend,
            $campaignEffect->usageLedger,
        );
    }

    public function reverseCoupon(
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $ledger,
        string $couponCode,
        string $customerId,
        string $orderId,
    ): PromotionCheckoutReversalResultDTO {
        return $this->couponOperationService->reverse(
            $couponBook,
            $ledger,
            $couponCode,
            $customerId,
            $orderId,
        );
    }
}
