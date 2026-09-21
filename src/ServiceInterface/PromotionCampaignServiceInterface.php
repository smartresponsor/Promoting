<?php

declare(strict_types=1);

namespace App\Promoting\ServiceInterface;

use App\Promoting\DTO\PromotionCampaignEvaluationDTO;
use App\Promoting\ValueObject\PromotionCampaign;

/** Controls campaign lifecycle, availability, and bounded spend. */
interface PromotionCampaignServiceInterface
{
    /** Evaluates lifecycle, time window, and budget at an explicit instant. */
    public function evaluate(
        PromotionCampaign $campaign,
        \DateTimeImmutable $at,
    ): PromotionCampaignEvaluationDTO;

    /** Activates an inactive or paused campaign when current constraints permit it. */
    public function activate(PromotionCampaign $campaign, \DateTimeImmutable $at): PromotionCampaign;

    /** Pauses an active campaign without changing spend or window state. */
    public function pause(PromotionCampaign $campaign): PromotionCampaign;

    /** Resumes a paused campaign when current constraints permit it. */
    public function resume(PromotionCampaign $campaign, \DateTimeImmutable $at): PromotionCampaign;

    /** Adds spend atomically at the value-object boundary and rejects budget overflow. */
    public function recordSpend(PromotionCampaign $campaign, int $amountMinor): PromotionCampaign;
}
