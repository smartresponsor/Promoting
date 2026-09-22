<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionCouponResolutionResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionCouponResolutionServiceInterface;
use App\Promoting\ServiceInterface\PromotionCouponServiceInterface;
use App\Promoting\ServiceInterface\PromotionEvaluationServiceInterface;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Resolves an issued coupon into an eligible promotion without mutating redemption state. */
final readonly class PromotionCouponResolutionService implements PromotionCouponResolutionServiceInterface
{
    public function __construct(
        private PromotionCouponServiceInterface $couponService,
        private PromotionEvaluationServiceInterface $evaluationService,
    ) {
    }

    public function resolve(
        PromotionCouponBook $couponBook,
        PromotionCatalog $promotionCatalog,
        PromotionRedemptionLedger $ledger,
        string $couponCode,
        string $customerId,
        PromotionEvaluationRequestDTO $request,
    ): PromotionCouponResolutionResultDTO {
        $coupon = $couponBook->find($couponCode);
        if (null === $coupon) {
            return new PromotionCouponResolutionResultDTO(false, null, null, ['coupon_not_found']);
        }

        $couponValidation = $this->couponService->validate(
            $coupon,
            $ledger,
            $customerId,
            $request->at,
        );
        if (!$couponValidation->valid) {
            return new PromotionCouponResolutionResultDTO(
                false,
                $coupon,
                null,
                $couponValidation->reasons,
            );
        }

        $promotion = $promotionCatalog->find($coupon->promotionId);
        if (null === $promotion) {
            return new PromotionCouponResolutionResultDTO(
                false,
                $coupon,
                null,
                [...$couponValidation->reasons, 'coupon_promotion_not_found'],
            );
        }

        $promotionEvaluation = $this->evaluationService->evaluate($promotion, $request);

        return new PromotionCouponResolutionResultDTO(
            $promotionEvaluation->eligible,
            $coupon,
            $promotion,
            [...$couponValidation->reasons, ...$promotionEvaluation->reasons],
        );
    }
}
