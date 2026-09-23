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

    /** Selects only promotions that are eligible for ordinary automatic activation. */
    public function select(
        PromotionCatalog $catalog,
        PromotionEvaluationRequestDTO $request,
    ): PromotionSelectionResultDTO {
        return $this->selectWithActivationModes(
            $catalog,
            $request,
            [PromotionActivationMode::Automatic],
        );
    }

    /** Selects promotions that may execute inside an active campaign context. */
    public function selectForCampaign(
        PromotionCatalog $catalog,
        PromotionEvaluationRequestDTO $request,
    ): PromotionSelectionResultDTO {
        return $this->selectWithActivationModes(
            $catalog,
            $request,
            [PromotionActivationMode::Automatic, PromotionActivationMode::Campaign],
        );
    }

    /**
     * @param list<PromotionActivationMode> $allowedModes
     */
    private function selectWithActivationModes(
        PromotionCatalog $catalog,
        PromotionEvaluationRequestDTO $request,
        array $allowedModes,
    ): PromotionSelectionResultDTO {
        $promotions = [];
        $evaluations = [];

        foreach ($catalog->promotions as $promotion) {
            if (!in_array($promotion->activationMode, $allowedModes, true)) {
                $evaluations[] = new PromotionEvaluationDTO(
                    $promotion->id,
                    false,
                    match ($promotion->activationMode) {
                        PromotionActivationMode::Coupon => ['promotion_coupon_required'],
                        PromotionActivationMode::Campaign => ['promotion_campaign_required'],
                        PromotionActivationMode::Automatic => ['promotion_activation_context_mismatch'],
                    },
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
