<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionEvaluationDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ValueObject\Promotion;

/** Defines deterministic eligibility evaluation for one promotion and caller-owned monetary context. */
interface PromotionEvaluationServiceInterface
{
    /** Evaluates lifecycle and rule conditions in stable declaration order. */
    public function evaluate(Promotion $promotion, PromotionEvaluationRequestDTO $request): PromotionEvaluationDTO;
}
