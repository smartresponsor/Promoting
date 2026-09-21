<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

/** Auditable deterministic eligibility decision for one promotion definition. */
final readonly class PromotionEvaluationDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public string $promotionId,
        public bool $eligible,
        public array $reasons,
    ) {
    }
}
