<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Unit;

use App\Promoting\DTO\PromotionApplicationResultDTO;
use App\Promoting\DTO\PromotionBenefitRequestDTO;
use App\Promoting\DTO\PromotionBenefitResultDTO;
use App\Promoting\DTO\PromotionEvaluationRequestDTO;
use App\Promoting\DTO\PromotionResolutionResultDTO;
use App\Promoting\Enum\PromotionApplicationMethod;
use App\Promoting\Enum\PromotionBenefitType;
use App\Promoting\Enum\PromotionCampaignStatus;
use App\Promoting\Enum\PromotionCouponStatus;
use App\Promoting\ValueObject\Promotion;
use App\Promoting\ValueObject\PromotionAction;
use App\Promoting\ValueObject\PromotionBenefit;
use App\Promoting\ValueObject\PromotionCampaign;
use App\Promoting\ValueObject\PromotionCatalog;
use App\Promoting\ValueObject\PromotionCondition;
use App\Promoting\ValueObject\PromotionCoupon;
use App\Promoting\ValueObject\PromotionCouponBook;
use App\Promoting\ValueObject\PromotionRedemption;
use App\Promoting\ValueObject\PromotionRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercises validation and immutable lifecycle invariants that protect promotion state. */
final class PromotionInvariantTest extends TestCase
{
    /** @return iterable<string, array{\Closure(): mixed}> */
    public static function invalidInvariantProvider(): iterable
    {
        yield 'negative application discount' => [static fn (): PromotionApplicationResultDTO => new PromotionApplicationResultDTO(
            'p',
            true,
            PromotionApplicationMethod::Fixed,
            100,
            -1,
            101,
            [],
        )];
        yield 'oversized application discount' => [static fn (): PromotionApplicationResultDTO => new PromotionApplicationResultDTO(
            'p',
            true,
            PromotionApplicationMethod::Fixed,
            100,
            101,
            -1,
            [],
        )];
        yield 'inconsistent application total' => [static fn (): PromotionApplicationResultDTO => new PromotionApplicationResultDTO(
            'p',
            true,
            PromotionApplicationMethod::Fixed,
            100,
            10,
            95,
            [],
        )];
        yield 'negative benefit subtotal' => [static fn (): PromotionBenefitRequestDTO => new PromotionBenefitRequestDTO(-1, 'USD', [])];
        yield 'invalid benefit currency' => [static fn (): PromotionBenefitRequestDTO => new PromotionBenefitRequestDTO(1, 'US', [])];
        yield 'empty benefit sku' => [static fn (): PromotionBenefitRequestDTO => new PromotionBenefitRequestDTO(1, 'USD', ['' => 1])];
        yield 'negative benefit quantity' => [static fn (): PromotionBenefitRequestDTO => new PromotionBenefitRequestDTO(1, 'USD', ['SKU' => -1])];
        yield 'negative reward quantity' => [static fn (): PromotionBenefitResultDTO => new PromotionBenefitResultDTO(
            'p',
            true,
            PromotionBenefitType::FreeGift,
            'SKU',
            -1,
            false,
            [],
        )];
        yield 'missing reward sku' => [static fn (): PromotionBenefitResultDTO => new PromotionBenefitResultDTO(
            'p',
            true,
            PromotionBenefitType::FreeGift,
            null,
            1,
            false,
            [],
        )];
        yield 'negative evaluation subtotal' => [static fn (): PromotionEvaluationRequestDTO => new PromotionEvaluationRequestDTO(-1, 'USD')];
        yield 'invalid evaluation currency' => [static fn (): PromotionEvaluationRequestDTO => new PromotionEvaluationRequestDTO(1, 'US')];
        yield 'negative resolution discount' => [static fn (): PromotionResolutionResultDTO => new PromotionResolutionResultDTO(100, -1, 101, [])];
        yield 'negative resolution final amount' => [static fn (): PromotionResolutionResultDTO => new PromotionResolutionResultDTO(100, 101, -1, [])];
        yield 'inconsistent resolution total' => [static fn (): PromotionResolutionResultDTO => new PromotionResolutionResultDTO(100, 10, 95, [])];
        yield 'negative fixed action' => [static fn (): PromotionAction => PromotionAction::fixed(-1)];
        yield 'negative percentage action' => [static fn (): PromotionAction => PromotionAction::percentage(-1)];
        yield 'percentage above maximum' => [static fn (): PromotionAction => PromotionAction::percentage(10_001)];
        yield 'negative subtotal condition' => [static fn (): PromotionCondition => PromotionCondition::minimumSubtotal(-1)];
        yield 'invalid condition currency' => [static fn (): PromotionCondition => PromotionCondition::currency('US')];
        yield 'empty bxgy qualifying sku' => [static fn (): PromotionBenefit => PromotionBenefit::buyXGetY('', 1, 'SKU', 1)];
        yield 'empty bxgy reward sku' => [static fn (): PromotionBenefit => PromotionBenefit::buyXGetY('SKU', 1, '', 1)];
        yield 'zero bxgy qualifying quantity' => [static fn (): PromotionBenefit => PromotionBenefit::buyXGetY('SKU', 0, 'SKU', 1)];
        yield 'zero bxgy reward quantity' => [static fn (): PromotionBenefit => PromotionBenefit::buyXGetY('SKU', 1, 'SKU', 0)];
        yield 'empty gift sku' => [static fn (): PromotionBenefit => PromotionBenefit::freeGift('')];
        yield 'zero gift quantity' => [static fn (): PromotionBenefit => PromotionBenefit::freeGift('SKU', 0)];
        yield 'empty promotion id' => [static fn (): Promotion => new Promotion('', 'Label', new PromotionRule(), PromotionAction::fixed(0))];
        yield 'empty promotion label' => [static fn (): Promotion => new Promotion('id', '', new PromotionRule(), PromotionAction::fixed(0))];
        yield 'invalid promotion window' => [static fn (): Promotion => new Promotion(
            'id',
            'Label',
            new PromotionRule(),
            PromotionAction::fixed(0),
            startsAt: new \DateTimeImmutable('2026-10-02T00:00:00+00:00'),
            endsAt: new \DateTimeImmutable('2026-10-01T00:00:00+00:00'),
        )];
        yield 'empty campaign identity' => [static fn (): PromotionCampaign => new PromotionCampaign('', 'Label', ['p'])];
        yield 'empty campaign members' => [static fn (): PromotionCampaign => new PromotionCampaign('id', 'Label', [])];
        yield 'blank campaign member' => [static fn (): PromotionCampaign => new PromotionCampaign('id', 'Label', [''])];
        yield 'invalid campaign window' => [static fn (): PromotionCampaign => new PromotionCampaign(
            'id',
            'Label',
            ['p'],
            new \DateTimeImmutable('2026-10-02T00:00:00+00:00'),
            new \DateTimeImmutable('2026-10-01T00:00:00+00:00'),
        )];
        yield 'zero campaign budget' => [static fn (): PromotionCampaign => new PromotionCampaign('id', 'Label', ['p'], budgetMinor: 0)];
        yield 'negative campaign spend' => [static fn (): PromotionCampaign => new PromotionCampaign('id', 'Label', ['p'], spentMinor: -1)];
        yield 'campaign spend above budget' => [static fn (): PromotionCampaign => new PromotionCampaign('id', 'Label', ['p'], budgetMinor: 10, spentMinor: 11)];
        yield 'empty coupon code' => [static fn (): PromotionCoupon => new PromotionCoupon('', 'p')];
        yield 'empty coupon promotion id' => [static fn (): PromotionCoupon => new PromotionCoupon('CODE', '')];
        yield 'invalid coupon usage limit' => [static fn (): PromotionCoupon => new PromotionCoupon('CODE', 'p', usageLimit: 0)];
        yield 'invalid coupon customer limit' => [static fn (): PromotionCoupon => new PromotionCoupon('CODE', 'p', perCustomerLimit: 0)];
        yield 'invalid coupon window' => [static fn (): PromotionCoupon => new PromotionCoupon(
            'CODE',
            'p',
            startsAt: new \DateTimeImmutable('2026-10-02T00:00:00+00:00'),
            endsAt: new \DateTimeImmutable('2026-10-01T00:00:00+00:00'),
        )];
        yield 'blank coupon customer' => [static fn (): PromotionCoupon => new PromotionCoupon('CODE', 'p', customerId: ' ')];
        yield 'empty redemption code' => [static fn (): PromotionRedemption => new PromotionRedemption('', 'c', 'o')];
        yield 'empty redemption customer' => [static fn (): PromotionRedemption => new PromotionRedemption('CODE', '', 'o')];
        yield 'empty redemption order' => [static fn (): PromotionRedemption => new PromotionRedemption('CODE', 'c', '')];
    }

    #[DataProvider('invalidInvariantProvider')]
    public function testInvalidInvariantThrows(\Closure $operation): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $operation();
    }

    public function testImmutableLifecycleCopiesPreserveState(): void
    {
        $promotion = new Promotion('p', 'Promotion', new PromotionRule(), PromotionAction::fixed(10));
        self::assertSame($promotion->activationMode, $promotion->withStatus($promotion->status)->activationMode);

        $campaign = new PromotionCampaign('c', 'Campaign', ['p'], budgetMinor: 100);
        self::assertSame(PromotionCampaignStatus::Active, $campaign->withStatus(PromotionCampaignStatus::Active)->status);
        self::assertSame(25, $campaign->withSpentMinor(25)->spentMinor);

        $coupon = new PromotionCoupon('code', 'p');
        self::assertSame(PromotionCouponStatus::Inactive, $coupon->withStatus(PromotionCouponStatus::Inactive)->status);

        $redemption = new PromotionRedemption('code', 'customer', 'order');
        self::assertSame('CODE|customer|order', $redemption->key());
        self::assertNotSame($redemption->status, $redemption->reversed()->status);

        self::assertSame(PromotionBenefitType::FreeShipping, PromotionBenefit::freeShipping()->type);
    }

    public function testCatalogAndCouponBookMutationGuards(): void
    {
        $promotion = new Promotion('p', 'Promotion', new PromotionRule(), PromotionAction::fixed(10));
        $catalog = new PromotionCatalog([$promotion]);
        self::assertSame($promotion, $catalog->find('p'));

        try {
            $catalog->add($promotion);
            self::fail('Duplicate promotion must be rejected.');
        } catch (\DomainException $exception) {
            self::assertSame('Promotion id "p" already exists.', $exception->getMessage());
        }

        $coupon = new PromotionCoupon('code', 'p');
        $book = new PromotionCouponBook([$coupon]);
        self::assertSame($coupon, $book->find(' CODE '));

        try {
            $book->issue($coupon);
            self::fail('Duplicate coupon must be rejected.');
        } catch (\DomainException $exception) {
            self::assertSame('Coupon code "CODE" is already issued.', $exception->getMessage());
        }

        try {
            $book->replace(new PromotionCoupon('missing', 'p'));
            self::fail('Missing coupon replacement must be rejected.');
        } catch (\DomainException $exception) {
            self::assertSame('Coupon code "MISSING" is not issued.', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        new PromotionCouponBook([$coupon, $coupon]);
    }
}
