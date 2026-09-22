<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionEvaluationDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\DTO\PromotionSelectionResultDTO;
use App\Promoting\Enum\PromotionActivationMode;
use App\Promoting\ServiceInterface\PromotionEvaluationServiceInterface;
use App\Promoting\ServiceInterface\PromotionSelectionServiceInterface;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionCatalog;

/** Selects eligible promotions by priority descending and id ascending while preserving all evaluations. */
final readonly class PromotionSelectionService implements PromotionSelectionServiceInterface
{
    public function __construct(private PromotionEvaluationServiceInterface $evaluationService)
    {
    }

    public function select(
        PromotionCatalog $catalog,
        PromotionEvaluationRequestDTO $request,
    ): PromotionSelectionResultDTO {
        $promotions = [];
        $evaluations = [];

        foreach ($catalog->promotions as $promotion) {
            if (PromotionActivationMode::Coupon === $promotion->activationMode) {
                $evaluations[] = new PromotionEvaluationDTO(
                    $promotion->id,
                    false,
                    ['promotion_coupon_required'],
                );
                continue;
            }

            $evaluation = $this->evaluationService->evaluate($promotion, $request);
            $evaluations[] = $evaluation;
            if ($evaluation->eligible) {
                $promotions[] = $promotion;
            }
        }

        usort(
            $promotions,
            static fn (Promotion $left, Promotion $right): int => $right->priority <=> $left->priority
                ?: $left->id <=> $right->id,
        );

        return new PromotionSelectionResultDTO($promotions, $evaluations);
    }
}
