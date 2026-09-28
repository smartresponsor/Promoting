<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionCheckoutCouponEffectResultDTO;
use App\Promoting\DTO\PromotionCheckoutCouponPlanningResultDTO;
use App\Promoting\DTO\PromotionCheckoutPlanResultDTO;
use App\Promoting\DTO\PromotionCheckoutReversalResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionCouponServiceInterface;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Owns coupon-specific checkout replay, redemption, and reversal operations. */
final readonly class PromotionCheckoutCouponOperationService
{
    public function __construct(private PromotionCouponServiceInterface $couponService)
    {
    }

    public function preparePlanning(
        PromotionRedemptionLedger $ledger,
        ?string $couponCode,
        ?string $customerId,
        ?string $orderId,
    ): PromotionCheckoutCouponPlanningResultDTO {
        if (null === $couponCode) {
            return new PromotionCheckoutCouponPlanningResultDTO($ledger, false);
        }
        if (null === $customerId || '' === trim($customerId)) {
            throw new \InvalidArgumentException('Customer id is required when a coupon code is provided.');
        }

        $orderId = $this->requireOrderId($orderId);
        $existing = $ledger->findActive($couponCode, $customerId, $orderId);

        return new PromotionCheckoutCouponPlanningResultDTO(
            null === $existing ? $ledger : $ledger->reverse($couponCode, $customerId, $orderId),
            null !== $existing,
        );
    }

    public function apply(
        PromotionRedemptionLedger $ledger,
        PromotionCheckoutPlanResultDTO $plan,
        ?string $couponCode,
        ?string $customerId,
        ?string $orderId,
        PromotionEvaluationRequestDTO $request,
        bool $replay,
    ): PromotionCheckoutCouponEffectResultDTO {
        if (null === $couponCode || null === $plan->couponResolution) {
            return new PromotionCheckoutCouponEffectResultDTO(
                $ledger,
                null,
                ['checkout_application_completed_without_coupon'],
            );
        }

        $resolution = $plan->couponResolution;
        if (!$resolution->eligible || null === $resolution->coupon || null === $resolution->promotion) {
            if ($replay) {
                throw new \DomainException('Coupon replay no longer matches the current checkout plan.');
            }

            return new PromotionCheckoutCouponEffectResultDTO(
                $ledger,
                null,
                [...$resolution->reasons, 'checkout_coupon_not_redeemed'],
            );
        }
        if (!$this->promotionApplied($plan, $resolution->promotion->id)) {
            if ($replay) {
                throw new \DomainException('Coupon replay no longer matches the current checkout plan.');
            }

            return new PromotionCheckoutCouponEffectResultDTO(
                $ledger,
                null,
                ['checkout_coupon_not_reached_by_resolution'],
            );
        }

        $redemption = $this->couponService->redeem(
            $resolution->coupon,
            $ledger,
            (string) $customerId,
            $this->requireOrderId($orderId),
            $request->at,
        );

        return new PromotionCheckoutCouponEffectResultDTO(
            $redemption->ledger,
            $redemption,
            [
                ...$redemption->reasons,
                $redemption->redeemed ? 'checkout_coupon_redeemed' : 'checkout_coupon_redemption_failed',
            ],
        );
    }

    public function reverse(
        PromotionCouponBook $couponBook,
        PromotionRedemptionLedger $ledger,
        string $couponCode,
        string $customerId,
        string $orderId,
    ): PromotionCheckoutReversalResultDTO {
        if ('' === trim($couponCode)) {
            throw new \InvalidArgumentException('Coupon code cannot be empty.');
        }
        if ('' === trim($customerId)) {
            throw new \InvalidArgumentException('Customer id cannot be empty.');
        }
        if ('' === trim($orderId)) {
            throw new \InvalidArgumentException('Order id cannot be empty.');
        }

        $coupon = $couponBook->find($couponCode);
        if (null === $coupon) {
            return new PromotionCheckoutReversalResultDTO(
                $ledger,
                null,
                ['checkout_coupon_reversal_coupon_not_found'],
            );
        }

        $reversal = $this->couponService->reverse($coupon, $ledger, $customerId, $orderId);

        return new PromotionCheckoutReversalResultDTO(
            $reversal->ledger,
            $reversal,
            $reversal->redeemed
                ? [...$reversal->reasons, 'checkout_coupon_reversal_completed']
                : [...$reversal->reasons, 'checkout_coupon_reversal_idempotent_noop'],
        );
    }

    private function promotionApplied(PromotionCheckoutPlanResultDTO $plan, string $promotionId): bool
    {
        foreach ($plan->resolution->applications as $application) {
            if ($application->eligible && $application->promotionId === $promotionId) {
                return true;
            }
        }

        return false;
    }

    private function requireOrderId(?string $orderId): string
    {
        if (null === $orderId || '' === trim($orderId)) {
            throw new \InvalidArgumentException('Order id is required when coupon or campaign context is provided.');
        }

        return $orderId;
    }
}
