<?php

declare(strict_types=1);

namespace Gcego\Core\Tests\Fixtures;

use Closure;
use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Contracts\ExtensionInterface;
use Gcego\Core\Extensions\ExtensionDescriptor;

final readonly class FixtureExtension implements ExtensionInterface
{
    /**
     * @param  (Closure(BootstrapContext): void)|null  $onActivate
     */
    public function __construct(
        private ExtensionDescriptor $descriptor,
        private BootLog $log = new BootLog,
        private ?Closure $onActivate = null,
    ) {}

    /**
     * @param  list<string>  $requires
     * @param  list<string>  $optional
     * @param  list<string>  $whenPackages
     * @param  list<string>  $capabilities
     */
    public static function make(string $id, array $requires = [], array $optional = [], array $whenPackages = [], array $capabilities = [], ?BootLog $log = null, ?Closure $onActivate = null): self
    {
        return new self(new ExtensionDescriptor($id, $requires, $optional, $whenPackages, $capabilities), $log ?? new BootLog, $onActivate);
    }

    public function descriptor(): ExtensionDescriptor
    {
        return $this->descriptor;
    }

    public function activate(BootstrapContext $context): void
    {
        $this->log->record('extension:'.$this->descriptor->id);

        if ($this->onActivate !== null) {
            ($this->onActivate)($context);
        }
    }
}
