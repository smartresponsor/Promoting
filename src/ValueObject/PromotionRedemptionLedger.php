<?php

declare(strict_types=1);

namespace App\Promoting\ValueObject;

use App\Promoting\Enum\PromotionRedemptionStatus;

/** Immutable ledger of coupon redemptions with idempotent redeem and reverse operations. */
final readonly class PromotionRedemptionLedger
{
    /** @param list<PromotionRedemption> $entries */
    public function __construct(public array $entries = [])
    {
    }

    /** Counts active redemptions for a coupon. */
    public function redeemedCount(string $couponCode): int
    {
        $normalizedCode = strtoupper(trim($couponCode));

        return count(array_filter(
            $this->entries,
            static fn (PromotionRedemption $entry): bool => $normalizedCode === strtoupper($entry->couponCode)
                && PromotionRedemptionStatus::Redeemed === $entry->status,
        ));
    }

    /** Counts active redemptions for one coupon/customer pair. */
    public function redeemedCountForCustomer(string $couponCode, string $customerId): int
    {
        $normalizedCode = strtoupper(trim($couponCode));

        return count(array_filter(
            $this->entries,
            static fn (PromotionRedemption $entry): bool => $normalizedCode === strtoupper($entry->couponCode)
                && $customerId === $entry->customerId
                && PromotionRedemptionStatus::Redeemed === $entry->status,
        ));
    }

    /** Returns an active redemption matching the idempotency key, if present. */
    public function findActive(string $couponCode, string $customerId, string $orderId): ?PromotionRedemption
    {
        $key = (new PromotionRedemption($couponCode, $customerId, $orderId))->key();
        foreach ($this->entries as $entry) {
            if ($key === $entry->key() && PromotionRedemptionStatus::Redeemed === $entry->status) {
                return $entry;
            }
        }

        return null;
    }

    /** Adds a redemption once; replaying the same key returns the identical ledger. */
    public function redeem(PromotionRedemption $redemption): self
    {
        if (null !== $this->findActive($redemption->couponCode, $redemption->customerId, $redemption->orderId)) {
            return $this;
        }

        return new self([...$this->entries, $redemption]);
    }

    /** Reverses an active redemption once and leaves unrelated entries unchanged. */
    public function reverse(string $couponCode, string $customerId, string $orderId): self
    {
        $key = (new PromotionRedemption($couponCode, $customerId, $orderId))->key();
        $changed = false;
        $entries = [];
        foreach ($this->entries as $entry) {
            if (!$changed && $key === $entry->key() && PromotionRedemptionStatus::Redeemed === $entry->status) {
                $entries[] = $entry->reversed();
                $changed = true;
                continue;
            }
            $entries[] = $entry;
        }

        return $changed ? new self($entries) : $this;
    }
}
