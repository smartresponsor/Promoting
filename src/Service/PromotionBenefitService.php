<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionBenefitResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Enum\PromotionBenefitType;
use App\Promoting\ServiceInterface\PromotionBenefitServiceInterface;
use App\Promoting\ServiceInterface\PromotionEvaluationServiceInterface;
use App\Promoting\ValueObject\Promotion;

/** Produces typed BXGY, free-gift, and free-shipping facts while leaving fulfillment to downstream owners. */
final readonly class PromotionBenefitService implements PromotionBenefitServiceInterface
{
    public function __construct(private PromotionEvaluationServiceInterface $evaluationService)
    {
    }

    public function evaluate(
        Promotion $promotion,
        PromotionBenefitRequestDTO $request,
    ): PromotionBenefitResultDTO {
        $evaluation = $this->evaluationService->evaluate(
            $promotion,
            new PromotionEvaluationRequestDTO($request->subtotalMinor, $request->currencyCode, $request->at),
        );

        if (!$evaluation->eligible || null === $promotion->benefit) {
            return new PromotionBenefitResultDTO(
                $promotion->id,
                false,
                $promotion->benefit?->type,
                null,
                0,
                false,
                null === $promotion->benefit
                    ? [...$evaluation->reasons, 'promotion_benefit_missing']
                    : [...$evaluation->reasons, 'promotion_benefit_not_applied'],
            );
        }

        $benefit = $promotion->benefit;

        if (PromotionBenefitType::BuyXGetY === $benefit->type) {
            return $this->buyXGetY($promotion, $request, $evaluation->reasons);
        }

        if (PromotionBenefitType::FreeGift === $benefit->type) {
            return new PromotionBenefitResultDTO(
                $promotion->id,
                true,
                $benefit->type,
                $benefit->rewardSku,
                $benefit->rewardQuantity,
                false,
                [...$evaluation->reasons, 'promotion_free_gift_granted'],
            );
        }

        return new PromotionBenefitResultDTO(
            $promotion->id,
            true,
            $benefit->type,
            null,
            0,
            true,
            [...$evaluation->reasons, 'promotion_free_shipping_eligible'],
        );
    }

    /** @param list<string> $evaluationReasons */
    private function buyXGetY(
        Promotion $promotion,
        PromotionBenefitRequestDTO $request,
        array $evaluationReasons,
    ): PromotionBenefitResultDTO {
        /** @var \App\Promoting\ValueObject\PromotionBenefit $benefit */
        $benefit = $promotion->benefit;
        /** @var non-empty-string $qualifyingSku */
        $qualifyingSku = $benefit->qualifyingSku;
        /** @var non-empty-string $rewardSku */
        $rewardSku = $benefit->rewardSku;

        $qualifyingQuantity = $request->itemQuantities[$qualifyingSku] ?? 0;
        $blocks = intdiv($qualifyingQuantity, $benefit->requiredQuantity);
        $rewardQuantity = $blocks * $benefit->rewardQuantity;

        return new PromotionBenefitResultDTO(
            $promotion->id,
            $rewardQuantity > 0,
            $benefit->type,
            $rewardQuantity > 0 ? $rewardSku : null,
            $rewardQuantity,
            false,
            $rewardQuantity > 0
                ? [...$evaluationReasons, 'promotion_buy_x_get_y_granted']
                : [...$evaluationReasons, 'promotion_buy_x_get_y_quantity_not_met'],
        );
    }
}
