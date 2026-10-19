<?php

declare(strict_types=1);

namespace Gcego\Luminaries;

/**
 * The result of resolving an identifier: the provider and icon that will be
 * rendered, plus the alias chain that led there.
 */
final readonly class ResolvedIcon
{
    /**
     * @param  list<string>  $aliasChain  Every alias followed, in order (empty for direct names).
     */
    public function __construct(
        public string $requested,
        public string $provider,
        public string $icon,
        public array $aliasChain = [],
    ) {}

    public function canonical(): string
    {
        return IconName::canonical($this->provider, $this->icon);
    }

    public function viaAlias(): bool
    {
        return $this->aliasChain !== [];
    }
}
