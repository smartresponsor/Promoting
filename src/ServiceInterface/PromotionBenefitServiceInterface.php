<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionBenefitResultDTO;
use App\Promoting\ValueObject\Promotion;

/** Evaluates non-price promotion benefits without executing downstream fulfillment. */
interface PromotionBenefitServiceInterface
{
    /** Returns a typed benefit fact after applying the promotion's ordinary eligibility rules. */
    public function evaluate(
        Promotion $promotion,
        PromotionBenefitRequestDTO $request,
    ): PromotionBenefitResultDTO;
}
