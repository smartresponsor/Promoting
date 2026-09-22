<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\ValueObject\Promotion;

/** Campaign-scoped promotion selection with availability and membership audit reasons. */
final readonly class PromotionCampaignSelectionResultDTO
{
    /**
     * @param list<Promotion>              $promotions
     * @param list<PromotionEvaluationDTO> $evaluations
     * @param list<string>                 $reasons
     */
    public function __construct(
        public bool $available,
        public array $promotions,
        public array $evaluations,
        public array $reasons,
    ) {
    }
}
