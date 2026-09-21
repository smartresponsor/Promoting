<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\DTO\PromotionResolutionResultDTO;
use App\Promoting\Enum\PromotionStackingMode;
use App\Promoting\ServiceInterface\PromotionApplicationServiceInterface;
use App\Promoting\ServiceInterface\PromotionResolutionServiceInterface;
use App\Promoting\ValueObject\Promotion;

/** Resolves promotions by priority descending and id ascending against the remaining subtotal. */
final readonly class PromotionResolutionService implements PromotionResolutionServiceInterface
{
    public function __construct(private PromotionApplicationServiceInterface $applicationService)
    {
    }

    public function resolve(array $promotions, PromotionEvaluationRequestDTO $request): PromotionResolutionResultDTO
    {
        usort(
            $promotions,
            static fn (Promotion $left, Promotion $right): int => $right->priority <=> $left->priority
                ?: $left->id <=> $right->id,
        );

        $remaining = $request->subtotalMinor;
        $applications = [];
        foreach ($promotions as $promotion) {
            $application = $this->applicationService->apply(
                $promotion,
                new PromotionEvaluationRequestDTO($remaining, $request->currencyCode, $request->at),
            );
            $applications[] = $application;
            $remaining = $application->finalAmountMinor;

            if ($application->eligible && PromotionStackingMode::Exclusive === $promotion->stackingMode) {
                break;
            }
            if (0 === $remaining) {
                break;
            }
        }

        return new PromotionResolutionResultDTO(
            $request->subtotalMinor,
            $request->subtotalMinor - $remaining,
            $remaining,
            $applications,
        );
    }
}
