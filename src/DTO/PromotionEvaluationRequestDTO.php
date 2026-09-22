<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

/** Carries caller-owned monetary context into promotion evaluation without taking pricing ownership. */
final readonly class PromotionEvaluationRequestDTO
{
    public string $currencyCode;

    public function __construct(
        public int $subtotalMinor,
        string $currencyCode,
        public ?\DateTimeImmutable $at = null,
    ) {
        if ($subtotalMinor < 0) {
            throw new \InvalidArgumentException('Promotion evaluation subtotal cannot be negative.');
        }

        $currencyCode = trim($currencyCode);
        $currencyCode = strtoupper($currencyCode);
        if (1 !== preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new \InvalidArgumentException('Currency code must contain exactly three ASCII letters.');
        }

        $this->currencyCode = $currencyCode;
    }
}
