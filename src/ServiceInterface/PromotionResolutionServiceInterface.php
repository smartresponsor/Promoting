<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\DTO\PromotionResolutionResultDTO;
use App\Promoting\ValueObject\Promotion;

/** Resolves multiple promotions using explicit priority and stacking semantics. */
interface PromotionResolutionServiceInterface
{
    /** @param list<Promotion> $promotions */
    public function resolve(
        array $promotions,
        PromotionEvaluationRequestDTO $request,
    ): PromotionResolutionResultDTO;
}
