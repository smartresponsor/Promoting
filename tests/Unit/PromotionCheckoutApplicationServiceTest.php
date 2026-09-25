<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionCouponRedemptionResultDTO;
use App\Promoting\DTO\PromotionCouponValidationDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\Enum\PromotionActivationMode;
use App\Promoting\Enum\PromotionCampaignStatus;
use App\Promoting\Enum\PromotionStackingMode;
use App\Promoting\Service\PromotionApplicationService;
use App\Promoting\Service\PromotionBenefitService;
use App\Promoting\Service\PromotionCampaignSelectionService;
use App\Promoting\Service\PromotionCampaignService;
use App\Promoting\Service\PromotionCampaignUsageService;
use App\Promoting\Service\PromotionCheckoutApplicationService;
use App\Promoting\Service\PromotionCheckoutPlanService;
use App\Promoting\Service\PromotionCouponResolutionService;
use App\Promoting\Service\PromotionCouponService;
use App\Promoting\Service\PromotionEvaluationService;
use App\Promoting\Service\PromotionResolutionService;
use App\Promoting\Service\PromotionSelectionService;
use App\Promoting\ServiceInterface\PromotionCouponServiceInterface;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionBenefit;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCampaignSpend;
use App\Promoting\ValueObject\PromotionCampaignSpendLedger;
use App\Promoting\ValueObject\PromotionCampaignUsage;
use App\Promoting\ValueObject\PromotionCampaignUsageLedger;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCoupon;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemption;
use App\Promoting\ValueObject\PromotionRedemptionLedger;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\TestCase;

/** Verifies checkout application mutates only coupon redemption state after unified resolution. */
final class PromotionCheckoutApplicationServiceTest extends TestCase
{
    private function service(): PromotionCheckoutApplicationService
    {
        $evaluation = new PromotionEvaluationService();
        $coupon = new PromotionCouponService();
        $application = new PromotionApplicationService($evaluation);
        $selection = new PromotionSelectionService($evaluation);
        $campaign = new PromotionCampaignService();
        $plan = new PromotionCheckoutPlanService(
            $selection,
            new PromotionCampaignSelectionService($campaign, $selection),
            new PromotionCouponResolutionService($coupon, $evaluation),
            new PromotionResolutionService($application),
            new PromotionBenefitService($evaluation),
        );

        return new PromotionCheckoutApplicationService($plan, $coupon, $campaign, new PromotionCampaignUsageService());
    }

    private function request(): PromotionEvaluationRequestDTO
    {
        return new PromotionEvaluationRequestDTO(
            1000,
            'USD',
            new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
        );
    }

    private function benefitRequest(): PromotionBenefitRequestDTO
    {
        return new PromotionBenefitRequestDTO(
            1000,
            'USD',
            [],
            new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
        );
    }

    public function testCouponIsRedeemedWhenCouponPromotionParticipates(): void
    {
        $automatic = new Promotion(
            'automatic',
            'Automatic',
            new PromotionRule(),
            PromotionAction::fixed(100),
            priority: 10,
        );
        $couponPromotion = new Promotion(
            'coupon',
            'Coupon',
            new PromotionRule(),
            PromotionAction::fixed(200),
            priority: 100,
            activationMode: PromotionActivationMode::Coupon,
        );
        $coupon = new PromotionCoupon('SAVE', 'coupon', usageLimit: 1, perCustomerLimit: 1);

        $result = $this->service()->apply(
            new PromotionCatalog([$automatic, $couponPromotion]),
            new PromotionCouponBook([$coupon]),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'save',
            'customer',
            'order-1',
        );

        self::assertNotNull($result->couponRedemption);
        self::assertTrue($result->couponRedemption->redeemed);
        self::assertSame(1, $result->ledger->redeemedCount('SAVE'));
        self::assertContains('checkout_coupon_redeemed', $result->reasons);
        self::assertSame(300, $result->plan->resolution->totalDiscountAmountMinor);
    }

    public function testCouponIsNotRedeemedWhenHigherPriorityExclusiveAutomaticPromotionStopsResolution(): void
    {
        $automatic = new Promotion(
            'exclusive-auto',
            'Exclusive Automatic',
            new PromotionRule(),
            PromotionAction::fixed(300),
            priority: 100,
            stackingMode: PromotionStackingMode::Exclusive,
        );
        $couponPromotion = new Promotion(
            'coupon',
            'Coupon',
            new PromotionRule(),
            PromotionAction::fixed(200),
            priority: 10,
            activationMode: PromotionActivationMode::Coupon,
        );
        $coupon = new PromotionCoupon('SAVE', 'coupon');

        $result = $this->service()->apply(
            new PromotionCatalog([$automatic, $couponPromotion]),
            new PromotionCouponBook([$coupon]),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'save',
            'customer',
            'order-1',
        );

        self::assertNull($result->couponRedemption);
        self::assertSame(0, $result->ledger->redeemedCount('SAVE'));
        self::assertSame(['checkout_coupon_not_reached_by_resolution'], $result->reasons);
        self::assertCount(1, $result->plan->resolution->applications);
        self::assertSame('exclusive-auto', $result->plan->resolution->applications[0]->promotionId);
    }

    public function testSameOrderReplayIsIdempotentEvenAtUsageLimit(): void
    {
        $couponPromotion = new Promotion(
            'coupon',
            'Coupon',
            new PromotionRule(),
            PromotionAction::fixed(200),
            activationMode: PromotionActivationMode::Coupon,
        );
        $coupon = new PromotionCoupon('ONCE', 'coupon', usageLimit: 1, perCustomerLimit: 1);
        $catalog = new PromotionCatalog([$couponPromotion]);
        $book = new PromotionCouponBook([$coupon]);

        $first = $this->service()->apply(
            $catalog,
            $book,
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'once',
            'customer',
            'order-1',
        );
        $replay = $this->service()->apply(
            $catalog,
            $book,
            $first->ledger,
            $this->request(),
            $this->benefitRequest(),
            'once',
            'customer',
            'order-1',
        );

        self::assertNotNull($replay->couponRedemption);
        self::assertTrue($replay->couponRedemption->redeemed);
        self::assertSame(['coupon_redemption_idempotent_replay'], $replay->couponRedemption->reasons);
        self::assertSame(1, $replay->ledger->redeemedCount('ONCE'));
    }

    public function testCouponReplayParticipationDriftIsRejected(): void
    {
        $couponPromotion = new Promotion(
            'coupon',
            'Coupon',
            new PromotionRule(),
            PromotionAction::fixed(200),
            priority: 10,
            activationMode: PromotionActivationMode::Coupon,
        );
        $coupon = new PromotionCoupon('SAVE', 'coupon');
        $book = new PromotionCouponBook([$coupon]);

        $first = $this->service()->apply(
            new PromotionCatalog([$couponPromotion]),
            $book,
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'save',
            'customer',
            'order-1',
        );

        $exclusiveAutomatic = new Promotion(
            'exclusive-auto',
            'Exclusive Automatic',
            new PromotionRule(),
            PromotionAction::fixed(300),
            priority: 100,
            stackingMode: PromotionStackingMode::Exclusive,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Coupon replay no longer matches the current checkout plan.');
        $this->service()->apply(
            new PromotionCatalog([$exclusiveAutomatic, $couponPromotion]),
            $book,
            $first->ledger,
            $this->request(),
            $this->benefitRequest(),
            'save',
            'customer',
            'order-1',
        );
    }

    public function testCouponReplayMissingDefinitionIsRejected(): void
    {
        $couponPromotion = new Promotion(
            'coupon',
            'Coupon',
            new PromotionRule(),
            PromotionAction::fixed(200),
            activationMode: PromotionActivationMode::Coupon,
        );
        $coupon = new PromotionCoupon('SAVE', 'coupon');

        $first = $this->service()->apply(
            new PromotionCatalog([$couponPromotion]),
            new PromotionCouponBook([$coupon]),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'save',
            'customer',
            'order-1',
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Coupon replay no longer matches the current checkout plan.');
        $this->service()->apply(
            new PromotionCatalog([$couponPromotion]),
            new PromotionCouponBook(),
            $first->ledger,
            $this->request(),
            $this->benefitRequest(),
            'save',
            'customer',
            'order-1',
        );
    }

    public function testInvalidCouponLeavesLedgerUnchanged(): void
    {
        $ledger = new PromotionRedemptionLedger();

        $result = $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            $ledger,
            $this->request(),
            $this->benefitRequest(),
            'missing',
            'customer',
            'order-1',
        );

        self::assertSame($ledger, $result->ledger);
        self::assertNull($result->couponRedemption);
        self::assertContains('coupon_not_found', $result->reasons);
        self::assertContains('checkout_coupon_not_redeemed', $result->reasons);
    }

    public function testCheckoutWithoutCouponLeavesLedgerUnchanged(): void
    {
        $automatic = new Promotion(
            'automatic',
            'Automatic',
            new PromotionRule(),
            PromotionAction::fixed(100),
        );
        $ledger = new PromotionRedemptionLedger();

        $result = $this->service()->apply(
            new PromotionCatalog([$automatic]),
            new PromotionCouponBook(),
            $ledger,
            $this->request(),
            $this->benefitRequest(),
        );

        self::assertSame($ledger, $result->ledger);
        self::assertNull($result->couponRedemption);
        self::assertSame(['checkout_application_completed_without_coupon'], $result->reasons);
        self::assertSame(100, $result->plan->resolution->totalDiscountAmountMinor);
    }

    public function testCampaignSpendIsRecordedFromUnifiedCheckoutResolution(): void
    {
        $campaignPromotion = new Promotion(
            'campaign-promo',
            'Campaign Promo',
            new PromotionRule(),
            PromotionAction::fixed(200),
            activationMode: PromotionActivationMode::Campaign,
        );
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['campaign-promo'],
            budgetMinor: 1000,
            status: PromotionCampaignStatus::Active,
        );
        $campaignLedger = new PromotionCampaignSpendLedger();

        $result = $this->service()->apply(
            new PromotionCatalog([$campaignPromotion]),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            orderId: 'order-1',
            campaign: $campaign,
            campaignSpendLedger: $campaignLedger,
        );

        self::assertNotNull($result->campaign);
        self::assertSame(200, $result->campaign->spentMinor);
        self::assertNotNull($result->campaignSpendLedger);
        self::assertSame(200, $result->campaignSpendLedger->activeSpendForCampaign('campaign'));
        self::assertNotNull($result->campaignSpend);
        self::assertSame(200, $result->campaignSpend->amountMinor);
        self::assertContains('checkout_campaign_spend_recorded', $result->reasons);
    }

    public function testCampaignSpendReplayIsIdempotent(): void
    {
        $campaignPromotion = new Promotion(
            'campaign-promo',
            'Campaign Promo',
            new PromotionRule(),
            PromotionAction::fixed(200),
            activationMode: PromotionActivationMode::Campaign,
        );
        $catalog = new PromotionCatalog([$campaignPromotion]);
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['campaign-promo'],
            budgetMinor: 1000,
            status: PromotionCampaignStatus::Active,
        );

        $first = $this->service()->apply(
            $catalog,
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            orderId: 'order-1',
            campaign: $campaign,
            campaignSpendLedger: new PromotionCampaignSpendLedger(),
        );
        self::assertNotNull($first->campaign);
        self::assertNotNull($first->campaignSpendLedger);

        $replay = $this->service()->apply(
            $catalog,
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            orderId: 'order-1',
            campaign: $first->campaign,
            campaignSpendLedger: $first->campaignSpendLedger,
        );

        self::assertSame($first->campaign, $replay->campaign);
        self::assertSame($first->campaignSpendLedger, $replay->campaignSpendLedger);
        self::assertNotNull($replay->campaignSpend);
        self::assertSame(200, $replay->campaignSpend->amountMinor);
        self::assertContains('campaign_spend_idempotent_replay', $replay->reasons);
    }

    public function testCampaignApplicationLimitFallsBackWithoutCampaignEffects(): void
    {
        $automatic = new Promotion(
            'automatic',
            'Automatic',
            new PromotionRule(),
            PromotionAction::fixed(100),
        );
        $campaignPromotion = new Promotion(
            'campaign-promo',
            'Campaign Promo',
            new PromotionRule(),
            PromotionAction::fixed(200),
            priority: 100,
            activationMode: PromotionActivationMode::Campaign,
        );
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['campaign-promo'],
            budgetMinor: 1000,
            status: PromotionCampaignStatus::Active,
            applicationLimit: 1,
        );
        $usageLedger = new PromotionCampaignUsageLedger([
            new PromotionCampaignUsage('campaign', 'order-existing'),
        ]);

        $result = $this->service()->apply(
            new PromotionCatalog([$automatic, $campaignPromotion]),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            orderId: 'order-2',
            campaign: $campaign,
            campaignSpendLedger: new PromotionCampaignSpendLedger(),
            campaignUsageLedger: $usageLedger,
        );

        self::assertSame(100, $result->plan->resolution->totalDiscountAmountMinor);
        self::assertContains('checkout_campaign_application_limit_reached', $result->plan->reasons);
        self::assertContains('checkout_campaign_usage_not_recorded_limit', $result->reasons);
        self::assertSame($usageLedger, $result->campaignUsageLedger);
        self::assertNotNull($result->campaign);
        self::assertSame(0, $result->campaign->spentMinor);
    }

    public function testBenefitOnlyCampaignConsumesApplicationUsageWithoutSpend(): void
    {
        $campaignPromotion = new Promotion(
            'campaign-gift',
            'Campaign Gift',
            new PromotionRule(),
            PromotionAction::fixed(0),
            benefit: PromotionBenefit::freeGift('GIFT'),
            activationMode: PromotionActivationMode::Campaign,
        );
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['campaign-gift'],
            budgetMinor: 1000,
            status: PromotionCampaignStatus::Active,
            applicationLimit: 1,
        );

        $result = $this->service()->apply(
            new PromotionCatalog([$campaignPromotion]),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            orderId: 'order-1',
            campaign: $campaign,
            campaignSpendLedger: new PromotionCampaignSpendLedger(),
            campaignUsageLedger: new PromotionCampaignUsageLedger(),
        );

        self::assertNotNull($result->campaignUsageLedger);
        self::assertSame(1, $result->campaignUsageLedger->activeCount('campaign'));
        self::assertContains('campaign_usage_recorded', $result->reasons);
        self::assertNull($result->campaignSpend);
        self::assertNotNull($result->campaign);
        self::assertSame(0, $result->campaign->spentMinor);
    }

    public function testApplicationLimitedCheckoutRequiresUsageLedger(): void
    {
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['campaign-promo'],
            status: PromotionCampaignStatus::Active,
            applicationLimit: 1,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            orderId: 'order-1',
            campaign: $campaign,
            campaignSpendLedger: new PromotionCampaignSpendLedger(),
        );
    }

    public function testExclusiveCouponCanDisplaceCampaignWithoutConsumingCampaignBudget(): void
    {
        $couponPromotion = new Promotion(
            'coupon',
            'Coupon',
            new PromotionRule(),
            PromotionAction::fixed(300),
            priority: 100,
            stackingMode: PromotionStackingMode::Exclusive,
            activationMode: PromotionActivationMode::Coupon,
        );
        $campaignPromotion = new Promotion(
            'campaign-promo',
            'Campaign Promo',
            new PromotionRule(),
            PromotionAction::fixed(200),
            priority: 10,
            activationMode: PromotionActivationMode::Campaign,
        );
        $coupon = new PromotionCoupon('SAVE', 'coupon');
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['campaign-promo'],
            budgetMinor: 1000,
            status: PromotionCampaignStatus::Active,
        );
        $campaignLedger = new PromotionCampaignSpendLedger();

        $result = $this->service()->apply(
            new PromotionCatalog([$couponPromotion, $campaignPromotion]),
            new PromotionCouponBook([$coupon]),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'SAVE',
            'customer',
            'order-1',
            $campaign,
            $campaignLedger,
        );

        self::assertNotNull($result->couponRedemption);
        self::assertTrue($result->couponRedemption->redeemed);
        self::assertSame(300, $result->plan->resolution->totalDiscountAmountMinor);
        self::assertNotNull($result->campaign);
        self::assertSame(0, $result->campaign->spentMinor);
        self::assertSame($campaignLedger, $result->campaignSpendLedger);
        self::assertNull($result->campaignSpend);
        self::assertContains('checkout_campaign_no_monetary_spend', $result->reasons);
    }

    public function testCampaignBudgetOverflowReplansWithoutCampaignPromotion(): void
    {
        $automatic = new Promotion(
            'automatic',
            'Automatic',
            new PromotionRule(),
            PromotionAction::fixed(100),
        );
        $campaignPromotion = new Promotion(
            'campaign-promo',
            'Campaign Promo',
            new PromotionRule(),
            PromotionAction::fixed(200),
            priority: 100,
            activationMode: PromotionActivationMode::Campaign,
        );
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['campaign-promo'],
            budgetMinor: 1000,
            spentMinor: 900,
            status: PromotionCampaignStatus::Active,
        );
        $campaignLedger = new PromotionCampaignSpendLedger([
            new PromotionCampaignSpend('campaign', 'order-existing', 900),
        ]);

        $result = $this->service()->apply(
            new PromotionCatalog([$automatic, $campaignPromotion]),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            orderId: 'order-1',
            campaign: $campaign,
            campaignSpendLedger: $campaignLedger,
        );

        self::assertSame(100, $result->plan->resolution->totalDiscountAmountMinor);
        self::assertNotNull($result->plan->campaignSelection);
        self::assertContains('checkout_campaign_budget_would_exceed', $result->plan->reasons);
        self::assertSame($campaign, $result->campaign);
        self::assertSame($campaignLedger, $result->campaignSpendLedger);
        self::assertNull($result->campaignSpend);
        self::assertContains('checkout_campaign_spend_not_recorded_budget', $result->reasons);
    }

    public function testCampaignContextRequiresSpendLedger(): void
    {
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['campaign-promo'],
            status: PromotionCampaignStatus::Active,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            orderId: 'order-1',
            campaign: $campaign,
        );
    }

    public function testCampaignContextRequiresOrderIdentity(): void
    {
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['campaign-promo'],
            status: PromotionCampaignStatus::Active,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            campaign: $campaign,
            campaignSpendLedger: new PromotionCampaignSpendLedger(),
        );
    }

    public function testCampaignSpendLedgerWithoutCampaignIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            campaignSpendLedger: new PromotionCampaignSpendLedger(),
        );
    }

    public function testCampaignAggregateAndLedgerDriftIsRejected(): void
    {
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['campaign-promo'],
            budgetMinor: 1000,
            spentMinor: 100,
            status: PromotionCampaignStatus::Active,
        );

        $this->expectException(\DomainException::class);
        $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            orderId: 'order-1',
            campaign: $campaign,
            campaignSpendLedger: new PromotionCampaignSpendLedger(),
        );
    }

    public function testCampaignReplayAmountDriftIsRejected(): void
    {
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['campaign-promo'],
            budgetMinor: 1000,
            spentMinor: 200,
            status: PromotionCampaignStatus::Active,
        );
        $ledger = new PromotionCampaignSpendLedger([
            new PromotionCampaignSpend('campaign', 'order-1', 200),
        ]);
        $changedPromotion = new Promotion(
            'campaign-promo',
            'Campaign Promo',
            new PromotionRule(),
            PromotionAction::fixed(300),
            activationMode: PromotionActivationMode::Campaign,
        );

        $this->expectException(\DomainException::class);
        $this->service()->apply(
            new PromotionCatalog([$changedPromotion]),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            orderId: 'order-1',
            campaign: $campaign,
            campaignSpendLedger: $ledger,
        );
    }

    public function testCampaignBenefitWithoutDiscountDoesNotCreateSpend(): void
    {
        $campaignPromotion = new Promotion(
            'campaign-gift',
            'Campaign Gift',
            new PromotionRule(),
            PromotionAction::fixed(0),
            benefit: PromotionBenefit::freeGift('GIFT'),
            activationMode: PromotionActivationMode::Campaign,
        );
        $campaign = new PromotionCampaign(
            'campaign',
            'Campaign',
            ['campaign-gift'],
            budgetMinor: 1000,
            status: PromotionCampaignStatus::Active,
        );
        $ledger = new PromotionCampaignSpendLedger();

        $result = $this->service()->apply(
            new PromotionCatalog([$campaignPromotion]),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            orderId: 'order-1',
            campaign: $campaign,
            campaignSpendLedger: $ledger,
        );

        self::assertCount(1, $result->plan->benefits);
        self::assertSame('GIFT', $result->plan->benefits[0]->rewardSku);
        self::assertSame($campaign, $result->campaign);
        self::assertSame($ledger, $result->campaignSpendLedger);
        self::assertNull($result->campaignSpend);
        self::assertContains('checkout_campaign_no_monetary_spend', $result->reasons);
    }

    public function testCheckoutCouponReversalReleasesRedemption(): void
    {
        $coupon = new PromotionCoupon('SAVE', 'coupon');
        $ledger = new PromotionRedemptionLedger([
            new PromotionRedemption('SAVE', 'customer', 'order-1'),
        ]);

        $result = $this->service()->reverseCoupon(
            new PromotionCouponBook([$coupon]),
            $ledger,
            'save',
            'customer',
            'order-1',
        );

        self::assertNotNull($result->couponReversal);
        self::assertTrue($result->couponReversal->redeemed);
        self::assertSame(0, $result->ledger->redeemedCount('SAVE'));
        self::assertSame(
            ['coupon_redemption_reversed', 'checkout_coupon_reversal_completed'],
            $result->reasons,
        );
    }

    public function testCheckoutCouponReversalReplayIsIdempotentNoop(): void
    {
        $coupon = new PromotionCoupon('SAVE', 'coupon');
        $ledger = new PromotionRedemptionLedger();

        $result = $this->service()->reverseCoupon(
            new PromotionCouponBook([$coupon]),
            $ledger,
            'SAVE',
            'customer',
            'order-1',
        );

        self::assertSame($ledger, $result->ledger);
        self::assertNotNull($result->couponReversal);
        self::assertFalse($result->couponReversal->redeemed);
        self::assertSame(
            ['coupon_reversal_idempotent_noop', 'checkout_coupon_reversal_idempotent_noop'],
            $result->reasons,
        );
    }

    public function testCheckoutCouponReversalMissingCouponLeavesLedgerUnchanged(): void
    {
        $ledger = new PromotionRedemptionLedger();

        $result = $this->service()->reverseCoupon(
            new PromotionCouponBook(),
            $ledger,
            'MISSING',
            'customer',
            'order-1',
        );

        self::assertSame($ledger, $result->ledger);
        self::assertNull($result->couponReversal);
        self::assertSame(['checkout_coupon_reversal_coupon_not_found'], $result->reasons);
    }

    public function testCheckoutCouponReversalRejectsBlankCouponCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->reverseCoupon(
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            '   ',
            'customer',
            'order-1',
        );
    }

    public function testCheckoutCouponReversalRejectsBlankCustomerId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->reverseCoupon(
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            'SAVE',
            '   ',
            'order-1',
        );
    }

    public function testCheckoutCouponReversalRejectsBlankOrderId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->reverseCoupon(
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            'SAVE',
            'customer',
            '   ',
        );
    }

    public function testCouponCodeRequiresCustomerIdentity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'SAVE',
            null,
            'order-1',
        );
    }

    public function testRedemptionBackendFailureKeepsLedgerUnchanged(): void
    {
        $evaluation = new PromotionEvaluationService();
        $planningCouponService = new PromotionCouponService();
        $application = new PromotionApplicationService($evaluation);
        $selection = new PromotionSelectionService($evaluation);
        $campaign = new PromotionCampaignService();
        $plan = new PromotionCheckoutPlanService(
            $selection,
            new PromotionCampaignSelectionService($campaign, $selection),
            new PromotionCouponResolutionService($planningCouponService, $evaluation),
            new PromotionResolutionService($application),
            new PromotionBenefitService($evaluation),
        );
        $failingCouponService = new class implements PromotionCouponServiceInterface {
            public function validate(
                PromotionCoupon $coupon,
                PromotionRedemptionLedger $ledger,
                string $customerId,
                ?\DateTimeImmutable $at = null,
            ): PromotionCouponValidationDTO {
                return new PromotionCouponValidationDTO(true, ['synthetic_valid']);
            }

            public function redeem(
                PromotionCoupon $coupon,
                PromotionRedemptionLedger $ledger,
                string $customerId,
                string $orderId,
                ?\DateTimeImmutable $at = null,
            ): PromotionCouponRedemptionResultDTO {
                return new PromotionCouponRedemptionResultDTO(
                    false,
                    $ledger,
                    null,
                    ['synthetic_redemption_failure'],
                );
            }

            public function reverse(
                PromotionCoupon $coupon,
                PromotionRedemptionLedger $ledger,
                string $customerId,
                string $orderId,
            ): PromotionCouponRedemptionResultDTO {
                return new PromotionCouponRedemptionResultDTO(false, $ledger, null, ['synthetic_reverse']);
            }
        };
        $service = new PromotionCheckoutApplicationService(
            $plan,
            $failingCouponService,
            $campaign,
            new PromotionCampaignUsageService(),
        );
        $couponPromotion = new Promotion(
            'coupon',
            'Coupon',
            new PromotionRule(),
            PromotionAction::fixed(200),
            activationMode: PromotionActivationMode::Coupon,
        );
        $coupon = new PromotionCoupon('SAVE', 'coupon');
        $ledger = new PromotionRedemptionLedger();

        $result = $service->apply(
            new PromotionCatalog([$couponPromotion]),
            new PromotionCouponBook([$coupon]),
            $ledger,
            $this->request(),
            $this->benefitRequest(),
            'SAVE',
            'customer',
            'order-1',
        );

        self::assertSame($ledger, $result->ledger);
        self::assertNotNull($result->couponRedemption);
        self::assertFalse($result->couponRedemption->redeemed);
        self::assertSame(
            ['synthetic_redemption_failure', 'checkout_coupon_redemption_failed'],
            $result->reasons,
        );
    }

    public function testCouponCodeRejectsBlankCustomerIdentity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'SAVE',
            '   ',
            'order-1',
        );
    }

    public function testCouponCodeRejectsBlankOrderIdentity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'SAVE',
            'customer',
            '   ',
        );
    }

    public function testCouponCodeRequiresOrderIdentity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->apply(
            new PromotionCatalog(),
            new PromotionCouponBook(),
            new PromotionRedemptionLedger(),
            $this->request(),
            $this->benefitRequest(),
            'SAVE',
            'customer',
        );
    }
}
