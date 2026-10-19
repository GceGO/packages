<?php

declare(strict_types=1);

namespace Gcego\Core\Bootstrap;

use Gcego\Core\Configuration\GcegoConfig;
use Illuminate\Contracts\Foundation\Application;

/**
 * What package boot hooks and extension activators receive.
 *
 * It describes the framework-wide boot only. It is not kept after boot and
 * carries no request, user or tenant state.
 */
final readonly class BootstrapContext
{
    /**
     * @param  list<string>  $packages  Ids of the packages taking part in this boot, in boot order.
     * @param  list<string>  $capabilities  Capabilities declared by those packages.
     */
    public function __construct(
        public Application $app,
        public GcegoConfig $config,
        public array $packages,
        public array $capabilities,
    ) {}

    public function hasPackage(string $id): bool
    {
        return in_array($id, $this->packages, true);
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }
}
