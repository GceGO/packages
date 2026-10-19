<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Contracts;

use Gcego\Luminaries\Exceptions\IconNotFoundException;
use Gcego\Luminaries\Support\ProviderMetadata;

/**
 * A source of icons addressed as "{name}:{icon}".
 *
 * has() and contents() run on the render path and must be cheap after the
 * first call; icons() is for discovery (CLI, explorer, manifest) and may scan.
 */
interface IconProvider
{
    /**
     * The namespace used in canonical identifiers, e.g. "lucide".
     */
    public function name(): string;

    public function has(string $icon): bool;

    /**
     * The trusted SVG markup of an icon, without Luminaries attributes.
     *
     * @throws IconNotFoundException
     */
    public function contents(string $icon): string;

    /**
     * The provider-specific name (e.g. the Blade Icons name "heroicon-o-pencil"), or null when there is none.
     */
    public function nativeName(string $icon): ?string;

    /**
     * Every available icon name (without the namespace), sorted.
     *
     * @return list<string>
     */
    public function icons(): array;

    public function metadata(): ProviderMetadata;
}
