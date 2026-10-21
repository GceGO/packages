<?php

declare(strict_types=1);

namespace Gcego\Core\Bootstrap;

/** Immutable preview. No package hooks or extension activators have run. */
final readonly class BootstrapPlan
{
    /**
     * @param  list<string>  $packages
     * @param  list<string>  $extensions
     * @param  array<string, string>  $skippedExtensions
     * @param  list<string>  $disabledPackages
     * @param  list<string>  $capabilities
     * @param  list<string>  $problems
     */
    public function __construct(
        public bool $enabled,
        public array $packages = [],
        public array $extensions = [],
        public array $skippedExtensions = [],
        public array $disabledPackages = [],
        public array $capabilities = [],
        public array $problems = [],
    ) {}

    public function isValid(): bool
    {
        return $this->problems === [];
    }

    /** @return array{enabled: bool, packages: list<string>, extensions: list<string>, skippedExtensions: array<string, string>, disabledPackages: list<string>, capabilities: list<string>, problems: list<string>} */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'packages' => $this->packages,
            'extensions' => $this->extensions,
            'skippedExtensions' => $this->skippedExtensions,
            'disabledPackages' => $this->disabledPackages,
            'capabilities' => $this->capabilities,
            'problems' => $this->problems,
        ];
    }
}
