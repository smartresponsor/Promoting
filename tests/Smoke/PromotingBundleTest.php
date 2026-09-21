<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Smoke;

use App\Promoting\PromotingBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Verifies the reusable Promoting bundle surface without introducing product behavior.
 */
final class PromotingBundleTest extends TestCase
{
    /**
     * Confirms the Symfony bundle contract required for host composition.
     */
    public function testBundleSurface(): void
    {
        self::assertInstanceOf(Bundle::class, new PromotingBundle());
    }
}
