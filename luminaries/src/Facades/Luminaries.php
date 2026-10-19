<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Facades;

use Gcego\Luminaries\Luminaries as LuminariesManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool exists(string|\BackedEnum $name)
 * @method static \Gcego\Luminaries\ResolvedIcon resolve(string|\BackedEnum $name)
 * @method static \Gcego\Luminaries\ResolvedIcon|null tryResolve(string|\BackedEnum $name)
 * @method static \Illuminate\Support\HtmlString render(string|\BackedEnum $name, ?string $size = null, ?string $tone = null, string $class = '', ?string $label = null, bool $decorative = false, ?float $strokeWidth = null, array<string, scalar|null> $attributes = [])
 * @method static \Illuminate\Support\HtmlString svg(string|\BackedEnum|null $name, string $class = '')
 * @method static bool hasProvider(string $name)
 * @method static \Gcego\Luminaries\Contracts\IconProvider|null provider(string $name)
 * @method static array<string, \Gcego\Luminaries\Contracts\IconProvider> providers()
 * @method static void registerProvider(\Gcego\Luminaries\Contracts\IconProvider $provider)
 * @method static void alias(string $alias, string $target, bool $override = false)
 * @method static array<string, string> aliases()
 * @method static void registerPath(string $name, string|list<string> $paths, bool $override = false)
 * @method static list<string> icons(string $provider)
 * @method static list<array{name: string, kind: string, provider: ?string, target: ?string}> search(string $query, ?string $provider = null, int $limit = 100, bool $aliases = true)
 *
 * @see LuminariesManager
 */
final class Luminaries extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LuminariesManager::class;
    }
}
