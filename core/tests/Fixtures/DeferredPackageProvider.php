<?php

declare(strict_types=1);

namespace Gcego\Core\Tests\Fixtures;

use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

final class DeferredPackageProvider extends ServiceProvider implements DeferrableProvider, PackageInterface
{
    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor('acme/deferred');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return ['acme.deferred'];
    }
}
