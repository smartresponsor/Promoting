<?php

declare(strict_types=1);

namespace App\Promoting\Tests\Smoke;

use App\Promoting\Kernel;
use PHPUnit\Framework\TestCase;

/** Verifies the standalone Promoting kernel resolves its repository root deterministically. */
final class KernelTest extends TestCase
{
    public function testProjectDirPointsToRepositoryRoot(): void
    {
        $kernel = new Kernel('test', true);

        self::assertSame(dirname(__DIR__, 2), $kernel->getProjectDir());
    }
}
