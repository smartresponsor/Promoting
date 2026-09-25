<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

/** Deterministic campaign usage-limit validation result. */
final readonly class PromotionCampaignUsageValidationDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public bool $allowed,
        public array $reasons,
    ) {
    }
}
