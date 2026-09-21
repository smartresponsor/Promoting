<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionApplicationResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Enum\PromotionApplicationMethod;
use App\Promoting\ServiceInterface\PromotionApplicationServiceInterface;
use App\Promoting\ServiceInterface\PromotionEvaluationServiceInterface;
use App\Promoting\ValueObject\Promotion;

/** Applies fixed or percentage incentives deterministically while preserving the caller's pricing ownership. */
final readonly class PromotionApplicationService implements PromotionApplicationServiceInterface
{
    public function __construct(private PromotionEvaluationServiceInterface $evaluationService)
    {
    }

    /** Applies an eligible promotion and caps every discount at the supplied subtotal. */
    public function apply(Promotion $promotion, PromotionEvaluationRequestDTO $request): PromotionApplicationResultDTO
    {
        $evaluation = $this->evaluationService->evaluate($promotion, $request);
        $discountAmountMinor = 0;
        $reasons = $evaluation->reasons;

        if ($evaluation->eligible) {
            $discountAmountMinor = match ($promotion->action->method) {
                PromotionApplicationMethod::Fixed => min($request->subtotalMinor, $promotion->action->amount),
                PromotionApplicationMethod::Percentage => $this->percentageDiscount(
                    $request->subtotalMinor,
                    $promotion->action->amount,
                ),
            };
            $reasons[] = 'promotion_applied_'.$promotion->action->method->value;
        } else {
            $reasons[] = 'promotion_not_applied';
        }

        return new PromotionApplicationResultDTO(
            promotionId: $promotion->id,
            eligible: $evaluation->eligible,
            method: $promotion->action->method,
            originalAmountMinor: $request->subtotalMinor,
            discountAmountMinor: $discountAmountMinor,
            finalAmountMinor: $request->subtotalMinor - $discountAmountMinor,
            reasons: $reasons,
        );
    }

    private function percentageDiscount(int $amountMinor, int $basisPoints): int
    {
        $whole = intdiv($amountMinor, 10_000) * $basisPoints;
        $remainder = intdiv(($amountMinor % 10_000) * $basisPoints, 10_000);

        return min($amountMinor, $whole + $remainder);
    }
}
