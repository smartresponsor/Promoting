<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionCouponApplicationResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\ServiceInterface\PromotionApplicationServiceInterface;
use App\Promoting\ServiceInterface\PromotionCouponApplicationServiceInterface;
use App\Promoting\ServiceInterface\PromotionCouponResolutionServiceInterface;
use App\Promoting\ServiceInterface\PromotionCouponServiceInterface;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemptionLedger;

/** Resolves, applies, and then redeems a coupon without hidden mutable state. */
final readonly class PromotionCouponApplicationService implements PromotionCouponApplicationServiceInterface
{
    public function __construct(
        private PromotionCouponResolutionServiceInterface $resolutionService,
        private PromotionApplicationServiceInterface $applicationService,
        private PromotionCouponServiceInterface $couponService,
    ) {
    }

    public function apply(
        PromotionCouponBook $couponBook,
        PromotionCatalog $promotionCatalog,
        PromotionRedemptionLedger $ledger,
        string $couponCode,
        string $customerId,
        string $orderId,
        PromotionEvaluationRequestDTO $request,
    ): PromotionCouponApplicationResultDTO {
        $coupon = $couponBook->find($couponCode);
        $validationLedger = $ledger;
        if (null !== $coupon && null !== $ledger->findActive($coupon->code, $customerId, $orderId)) {
            $validationLedger = $ledger->reverse($coupon->code, $customerId, $orderId);
        }

        $resolution = $this->resolutionService->resolve(
            $couponBook,
            $promotionCatalog,
            $validationLedger,
            $couponCode,
            $customerId,
            $request,
        );

        if (!$resolution->eligible || null === $resolution->coupon || null === $resolution->promotion) {
            return new PromotionCouponApplicationResultDTO(
                false,
                $ledger,
                null,
                null,
                [...$resolution->reasons, 'coupon_application_not_applied'],
            );
        }

        $application = $this->applicationService->apply($resolution->promotion, $request);
        if (!$application->eligible) {
            return new PromotionCouponApplicationResultDTO(
                false,
                $ledger,
                $application,
                null,
                [...$resolution->reasons, ...$application->reasons, 'coupon_application_not_applied'],
            );
        }

        $redemption = $this->couponService->redeem(
            $resolution->coupon,
            $ledger,
            $customerId,
            $orderId,
            $request->at,
        );

        if (!$redemption->redeemed) {
            return new PromotionCouponApplicationResultDTO(
                false,
                $ledger,
                $application,
                $redemption,
                [...$resolution->reasons, ...$application->reasons, ...$redemption->reasons, 'coupon_application_not_redeemed'],
            );
        }

        return new PromotionCouponApplicationResultDTO(
            true,
            $redemption->ledger,
            $application,
            $redemption,
            [...$resolution->reasons, ...$application->reasons, ...$redemption->reasons, 'coupon_application_applied'],
        );
    }
}
