<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

/** Immutable promotion catalog enforcing promotion-id uniqueness. */
final readonly class PromotionCatalog
{
    /** @param list<Promotion> $promotions */
    public function __construct(public array $promotions = [])
    {
        $seen = [];
        foreach ($promotions as $promotion) {
            if (isset($seen[$promotion->id])) {
                throw new \InvalidArgumentException(sprintf('Promotion id "%s" is duplicated.', $promotion->id));
            }
            $seen[$promotion->id] = true;
        }
    }

    /** Returns a promotion by exact id when present. */
    public function find(string $promotionId): ?Promotion
    {
        foreach ($this->promotions as $promotion) {
            if ($promotion->id === $promotionId) {
                return $promotion;
            }
        }

        return null;
    }

    /** Adds one uniquely identified promotion. */
    public function add(Promotion $promotion): self
    {
        if (null !== $this->find($promotion->id)) {
            throw new \DomainException(sprintf('Promotion id "%s" already exists.', $promotion->id));
        }

        return new self([...$this->promotions, $promotion]);
    }
}
