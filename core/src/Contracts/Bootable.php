<?php

declare(strict_types=1);

namespace Gcego\Core\Contracts;

use Gcego\Core\Bootstrap\BootstrapContext;

/**
 * Optional hook for packages that must initialize after their GCEGO dependencies.
 *
 * Laravel boots providers in registration order. bootPackage() runs once,
 * after every provider has booted, in GCEGO dependency order: a package's
 * required and optional dependencies always boot first.
 */
interface Bootable
{
    public function bootPackage(BootstrapContext $context): void;
}
