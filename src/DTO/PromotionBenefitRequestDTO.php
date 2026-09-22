<?php

declare(strict_types=1);

namespace App\Promoting\DTO;

/** Evaluation context for non-price promotion benefits. */
final readonly class PromotionBenefitRequestDTO
{
    public string $currencyCode;

    /**
     * @param array<string, int> $itemQuantities
     */
    public function __construct(
        public int $subtotalMinor,
        string $currencyCode,
        public array $itemQuantities,
        public ?\DateTimeImmutable $at = null,
    ) {
        if ($subtotalMinor < 0) {
            throw new \InvalidArgumentException('Subtotal cannot be negative.');
        }

        $normalizedCurrency = trim($currencyCode);
        $normalizedCurrency = strtoupper($normalizedCurrency);
        if (1 !== preg_match('/^[A-Z]{3}$/', $normalizedCurrency)) {
            throw new \InvalidArgumentException('Currency code must be a three-letter ISO-style code.');
        }

        foreach ($itemQuantities as $sku => $quantity) {
            if ('' === trim($sku) || $quantity < 0) {
                throw new \InvalidArgumentException('Item quantities require non-empty SKU keys and non-negative quantities.');
            }
        }

        $this->currencyCode = $normalizedCurrency;
    }
}
