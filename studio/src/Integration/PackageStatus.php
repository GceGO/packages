<?php

declare(strict_types=1);

namespace Gcego\Studio\Integration;

/**
 * One GCEGO package as Studio sees it: installed by Composer, its
 * provider loaded by Laravel's package discovery, registered and booted
 * by gcego/core.
 */
final readonly class PackageStatus
{
    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public string $id,
        public bool $required,
        public bool $installed,
        public ?string $version,
        public ?string $provider,
        public bool $providerLoaded,
        public bool $registered,
        public bool $active,
        public array $capabilities,
        public string $purpose,
    ) {}

    /**
     * Integrated: installed and, for packages with a Laravel provider, loaded, registered and booted.
     */
    public function integrated(): bool
    {
        return $this->installed && ($this->provider === null || ($this->providerLoaded && $this->registered && $this->active));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'required' => $this->required, 'installed' => $this->installed, 'version' => $this->version,
            'provider' => $this->provider, 'provider_loaded' => $this->providerLoaded, 'registered' => $this->registered, 'active' => $this->active,
            'integrated' => $this->integrated(), 'capabilities' => $this->capabilities, 'purpose' => $this->purpose,
        ];
    }
}
