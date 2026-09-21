<?php

declare(strict_types=1);

namespace App\Promoting;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Boots Promoting as a standalone Symfony application for verification and debugging.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Returns the repository root used by standalone Symfony configuration discovery.
     */
    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }
}
