<?php

declare(strict_types=1);

namespace Gcego\Core\Tests\Fixtures;

use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;

/**
 * A package without a boot hook: it only contributes metadata.
 */
final readonly class PlainPackage implements PackageInterface
{
    public function __construct(private PackageDescriptor $descriptor) {}

    public function descriptor(): PackageDescriptor
    {
        return $this->descriptor;
    }
}
