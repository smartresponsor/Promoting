<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionApplicationResultDTO;
use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionBenefitResultDTO;
use App\Promoting\ServiceInterface\PromotionBenefitServiceInterface;
use App\Promoting\ValueObject\Promotion;

/** Resolves typed promotion benefits only for applications reached by final checkout resolution. */
final readonly class PromotionCheckoutBenefitResolutionService
{
    public function __construct(private PromotionBenefitServiceInterface $benefitService)
    {
    }

    /**
     * @param list<Promotion>                     $promotions
     * @param list<PromotionApplicationResultDTO> $applications
     *
     * @return list<PromotionBenefitResultDTO>
     */
    public function resolve(
        array $promotions,
        array $applications,
        PromotionBenefitRequestDTO $request,
    ): array {
        $promotionById = [];
        foreach ($promotions as $promotion) {
            $promotionById[$promotion->id] = $promotion;
        }

        $benefits = [];
        foreach ($applications as $application) {
            if (!$application->eligible) {
                continue;
            }

            $promotion = $promotionById[$application->promotionId] ?? null;
            if (null === $promotion || null === $promotion->benefit) {
                continue;
            }

            $benefit = $this->benefitService->evaluate($promotion, $request);
            if ($benefit->eligible) {
                $benefits[] = $benefit;
            }
        }

        return $benefits;
    }
}
