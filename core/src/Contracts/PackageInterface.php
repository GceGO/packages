<?php

declare(strict_types=1);

namespace Gcego\Core\Contracts;

use Gcego\Core\Packages\PackageDescriptor;

/**
 * Identifies an installed Composer package as a GCEGO framework package.
 *
 * Implement it on the package's Laravel service provider: GCEGO discovers
 * every loaded (non-deferred) provider implementing this interface when it
 * boots, so no separate registration or package list is needed. Objects that
 * are not providers can be registered explicitly with PackageManager::register().
 */
interface PackageInterface
{
    public function descriptor(): PackageDescriptor;
}
