<?php

declare(strict_types=1);

namespace Gcego\Core\Tests\Fixtures;

use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class InvalidDescriptorProvider extends ServiceProvider implements PackageInterface
{
    public function descriptor(): PackageDescriptor
    {
        throw new RuntimeException('password=provider-secret');
    }
}
