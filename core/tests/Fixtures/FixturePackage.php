<?php

declare(strict_types=1);

namespace Gcego\Core\Tests\Fixtures;

use Closure;
use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Contracts\Bootable;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;

/**
 * A package object registered explicitly (not a service provider).
 */
final class FixturePackage implements Bootable, PackageInterface
{
    public int $descriptorCalls = 0;

    /**
     * @param  (Closure(BootstrapContext): void)|null  $onBoot
     */
    public function __construct(
        private readonly PackageDescriptor $descriptor,
        private readonly BootLog $log = new BootLog,
        private readonly ?Closure $onBoot = null,
    ) {}

    /**
     * @param  list<string>  $requires
     * @param  list<string>  $optional
     * @param  list<string>  $capabilities
     */
    public static function make(string $id, array $requires = [], array $optional = [], array $capabilities = [], ?BootLog $log = null, ?Closure $onBoot = null): self
    {
        return new self(new PackageDescriptor($id, requires: $requires, optional: $optional, capabilities: $capabilities), $log ?? new BootLog, $onBoot);
    }

    public function descriptor(): PackageDescriptor
    {
        $this->descriptorCalls++;

        return $this->descriptor;
    }

    public function bootPackage(BootstrapContext $context): void
    {
        $this->log->record('package:'.$this->descriptor->id);

        if ($this->onBoot !== null) {
            ($this->onBoot)($context);
        }
    }
}
