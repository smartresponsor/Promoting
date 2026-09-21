<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionApplicationResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ValueObject\Promotion;

/** Defines deterministic application of one eligible commercial incentive. */
interface PromotionApplicationServiceInterface
{
    /** Applies the configured action without changing caller-owned price or cart state. */
    public function apply(Promotion $promotion, PromotionEvaluationRequestDTO $request): PromotionApplicationResultDTO;
}
