<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

/** Deterministic multi-promotion resolution result in applied order. */
final readonly class PromotionResolutionResultDTO
{
    /** @param list<PromotionApplicationResultDTO> $applications */
    public function __construct(
        public int $originalAmountMinor,
        public int $totalDiscountAmountMinor,
        public int $finalAmountMinor,
        public array $applications,
    ) {
        if ($totalDiscountAmountMinor < 0 || $finalAmountMinor < 0) {
            throw new \InvalidArgumentException('Resolved monetary amounts cannot be negative.');
        }
        if ($originalAmountMinor - $totalDiscountAmountMinor !== $finalAmountMinor) {
            throw new \InvalidArgumentException('Resolved monetary amounts are inconsistent.');
        }
    }
}
