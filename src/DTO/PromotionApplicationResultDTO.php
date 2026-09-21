<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

use App\Promoting\Enum\PromotionApplicationMethod;

/** Auditable monetary result produced after deterministic promotion evaluation and application. */
final readonly class PromotionApplicationResultDTO
{
    /** @param list<string> $reasons */
    public function __construct(
        public string $promotionId,
        public bool $eligible,
        public PromotionApplicationMethod $method,
        public int $originalAmountMinor,
        public int $discountAmountMinor,
        public int $finalAmountMinor,
        public array $reasons,
    ) {
        if ($discountAmountMinor < 0 || $discountAmountMinor > $originalAmountMinor) {
            throw new \InvalidArgumentException('Promotion discount must be within the original amount.');
        }
        if ($finalAmountMinor !== $originalAmountMinor - $discountAmountMinor) {
            throw new \InvalidArgumentException('Promotion final amount must equal original amount minus discount.');
        }
    }
}
