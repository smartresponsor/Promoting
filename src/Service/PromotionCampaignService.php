<?php

declare(strict_types=1);

namespace App\Promoting\Service;

use App\Promoting\DTO\PromotionCampaignEvaluationDTO;
use App\Promoting\Enum\PromotionCampaignStatus;
use App\Promoting\ServiceInterface\PromotionCampaignServiceInterface;
use App\Promoting\ValueObject\PromotionCampaign;

/** Implements deterministic campaign lifecycle, window, and budget controls. */
final class PromotionCampaignService implements PromotionCampaignServiceInterface
{
    public function evaluate(
        PromotionCampaign $campaign,
        \DateTimeImmutable $at,
    ): PromotionCampaignEvaluationDTO {
        if (PromotionCampaignStatus::Active !== $campaign->status) {
            return new PromotionCampaignEvaluationDTO(false, ['campaign_not_active']);
        }

        $reasons = ['campaign_active'];
        if (null !== $campaign->startsAt && $at < $campaign->startsAt) {
            return new PromotionCampaignEvaluationDTO(false, [...$reasons, 'campaign_not_started']);
        }
        $reasons[] = 'campaign_start_window_met';

        if (null !== $campaign->endsAt && $at > $campaign->endsAt) {
            return new PromotionCampaignEvaluationDTO(false, [...$reasons, 'campaign_ended']);
        }
        $reasons[] = 'campaign_end_window_met';

        if (null !== $campaign->budgetMinor && $campaign->spentMinor >= $campaign->budgetMinor) {
            return new PromotionCampaignEvaluationDTO(false, [...$reasons, 'campaign_budget_exhausted']);
        }
        $reasons[] = 'campaign_budget_available';
        $reasons[] = 'campaign_available';

        return new PromotionCampaignEvaluationDTO(true, $reasons);
    }

    public function activate(PromotionCampaign $campaign, \DateTimeImmutable $at): PromotionCampaign
    {
        $candidate = $campaign->withStatus(PromotionCampaignStatus::Active);
        if (!$this->evaluate($candidate, $at)->available) {
            throw new \DomainException('Campaign cannot be activated outside its window or exhausted budget.');
        }

        return $candidate;
    }

    public function pause(PromotionCampaign $campaign): PromotionCampaign
    {
        if (PromotionCampaignStatus::Active !== $campaign->status) {
            return $campaign;
        }

        return $campaign->withStatus(PromotionCampaignStatus::Paused);
    }

    public function resume(PromotionCampaign $campaign, \DateTimeImmutable $at): PromotionCampaign
    {
        if (PromotionCampaignStatus::Paused !== $campaign->status) {
            return $campaign;
        }

        return $this->activate($campaign, $at);
    }

    public function recordSpend(PromotionCampaign $campaign, int $amountMinor): PromotionCampaign
    {
        if ($amountMinor < 0) {
            throw new \InvalidArgumentException('Campaign spend increment cannot be negative.');
        }

        $nextSpent = $campaign->spentMinor + $amountMinor;
        if (null !== $campaign->budgetMinor && $nextSpent > $campaign->budgetMinor) {
            throw new \DomainException('Campaign spend cannot exceed budget.');
        }

        return $campaign->withSpentMinor($nextSpent);
    }
}
