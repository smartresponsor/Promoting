<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\Promotion;

/** Deterministic eligible-promotion selection result with evaluations preserved for auditability. */
final readonly class PromotionSelectionResultDTO
{
    /**
     * @param list<Promotion>              $promotions
     * @param list<PromotionEvaluationDTO> $evaluations
     */
    public function __construct(
        public array $promotions,
        public array $evaluations,
    ) {
    }
}
