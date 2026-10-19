<?php

declare(strict_types=1);

namespace Gcego\Core\Tests\Fixtures;

use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Illuminate\Support\ServiceProvider;

/**
 * A package whose GCEGO dependency is not installed.
 */
final class BrokenServiceProvider extends ServiceProvider implements PackageInterface
{
    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor('gcego/broken', requires: ['gcego/missing']);
    }
}
