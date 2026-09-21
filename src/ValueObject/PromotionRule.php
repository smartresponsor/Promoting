<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

/** Immutable conjunction of promotion conditions evaluated in declaration order. */
final readonly class PromotionRule
{
    /** @param list<PromotionCondition> $conditions */
    public function __construct(public array $conditions = [])
    {
    }
}
