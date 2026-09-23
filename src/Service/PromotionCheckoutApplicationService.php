<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionCheckoutApplicationResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionCheckoutApplicationServiceInterface;
use App\Promoting\ServiceInterface\PromotionCheckoutPlanServiceInterface;
use App\Promoting\ServiceInterface\PromotionCouponServiceInterface;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Applies a unified checkout plan and records coupon redemption only when its promotion actually participates. */
final readonly class PromotionCheckoutApplicationService implements PromotionCheckoutApplicationServiceInterface
{
    public function __construct(
        private PromotionCheckoutPlanServiceInterface $planService,
        private PromotionCouponServiceInterface $couponService,
    ) {
    }

    public function apply(
        PromotionCatalog $catalog,
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $ledger,
        PromotionEvaluationRequestDTO $request,
        PromotionBenefitRequestDTO $benefitRequest,
        ?string $couponCode = null,
        ?string $customerId = null,
        ?string $orderId = null,
    ): PromotionCheckoutApplicationResultDTO {
        $planningLedger = $ledger;

        if (null !== $couponCode) {
            if (null === $customerId || '' === trim($customerId)) {
                throw new \InvalidArgumentException('Customer id is required when a coupon code is provided.');
            }
            if (null === $orderId || '' === trim($orderId)) {
                throw new \InvalidArgumentException('Order id is required when a coupon code is provided.');
            }

            $coupon = $couponBook->find($couponCode);
            if (null !== $coupon && null !== $ledger->findActive($coupon->code, $customerId, $orderId)) {
                $planningLedger = $ledger->reverse($coupon->code, $customerId, $orderId);
            }
        }

        $plan = $this->planService->plan(
            $catalog,
            $couponBook,
            $planningLedger,
            $request,
            $benefitRequest,
            $couponCode,
            $customerId,
        );

        if (null === $couponCode || null === $plan->couponResolution) {
            return new PromotionCheckoutApplicationResultDTO(
                $plan,
                $ledger,
                null,
                ['checkout_application_completed_without_coupon'],
            );
        }

        $couponResolution = $plan->couponResolution;
        if (
            !$couponResolution->eligible
            || null === $couponResolution->coupon
            || null === $couponResolution->promotion
        ) {
            return new PromotionCheckoutApplicationResultDTO(
                $plan,
                $ledger,
                null,
                [...$couponResolution->reasons, 'checkout_coupon_not_redeemed'],
            );
        }

        $couponPromotionApplied = false;
        foreach ($plan->resolution->applications as $application) {
            if (
                $application->eligible
                && $application->promotionId === $couponResolution->promotion->id
            ) {
                $couponPromotionApplied = true;
                break;
            }
        }

        if (!$couponPromotionApplied) {
            return new PromotionCheckoutApplicationResultDTO(
                $plan,
                $ledger,
                null,
                ['checkout_coupon_not_reached_by_resolution'],
            );
        }

        $redemption = $this->couponService->redeem(
            $couponResolution->coupon,
            $ledger,
            $customerId,
            $orderId,
            $request->at,
        );

        return new PromotionCheckoutApplicationResultDTO(
            $plan,
            $redemption->ledger,
            $redemption,
            $redemption->redeemed
                ? [...$redemption->reasons, 'checkout_coupon_redeemed']
                : [...$redemption->reasons, 'checkout_coupon_redemption_failed'],
        );
    }
}
