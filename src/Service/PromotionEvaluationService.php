<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionEvaluationDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Enum\PromotionConditionType;
use App\Promoting\Enum\PromotionStatus;
use App\Promoting\ServiceInterface\PromotionEvaluationServiceInterface;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionCondition;

/** Evaluates promotion lifecycle and rule conditions with stable, auditable reason codes. */
final class PromotionEvaluationService implements PromotionEvaluationServiceInterface
{
    /** Evaluates lifecycle first and then each declared condition without hidden external state. */
    public function evaluate(Promotion $promotion, PromotionEvaluationRequestDTO $request): PromotionEvaluationDTO
    {
        if (PromotionStatus::Active !== $promotion->status) {
            return new PromotionEvaluationDTO($promotion->id, false, ['promotion_inactive']);
        }

        $reasons = ['promotion_active'];
        if (null !== $promotion->startsAt || null !== $promotion->endsAt) {
            if (null === $request->at) {
                return new PromotionEvaluationDTO($promotion->id, false, [...$reasons, 'promotion_time_context_missing']);
            }
            if (null !== $promotion->startsAt && $request->at < $promotion->startsAt) {
                return new PromotionEvaluationDTO($promotion->id, false, [...$reasons, 'promotion_not_started']);
            }
            $reasons[] = 'promotion_start_window_met';
            if (null !== $promotion->endsAt && $request->at > $promotion->endsAt) {
                return new PromotionEvaluationDTO($promotion->id, false, [...$reasons, 'promotion_ended']);
            }
            $reasons[] = 'promotion_end_window_met';
        }

        foreach ($promotion->rule->conditions as $condition) {
            $reason = $this->evaluateCondition($condition, $request);
            $reasons[] = $reason;
            if (str_ends_with($reason, '_not_met') || str_ends_with($reason, '_mismatch')) {
                return new PromotionEvaluationDTO($promotion->id, false, $reasons);
            }
        }

        $reasons[] = 'promotion_eligible';

        return new PromotionEvaluationDTO($promotion->id, true, $reasons);
    }

    private function evaluateCondition(
        PromotionCondition $condition,
        PromotionEvaluationRequestDTO $request,
    ): string {
        return match ($condition->type) {
            PromotionConditionType::MinimumSubtotal => $request->subtotalMinor >= $condition->expected
                ? 'minimum_subtotal_met'
                : 'minimum_subtotal_not_met',
            PromotionConditionType::Currency => $request->currencyCode === $condition->expected
                ? 'currency_matched'
                : 'currency_mismatch',
        };
    }
}
