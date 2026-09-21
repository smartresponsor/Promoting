<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

/** Deterministic campaign availability result with stable reason codes. */
final readonly class PromotionCampaignEvaluationDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public bool $available,
        public array $reasons,
    ) {
    }
}
