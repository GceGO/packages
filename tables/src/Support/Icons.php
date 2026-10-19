<?php

declare(strict_types=1);

namespace Gcego\Tables\Support;

use BackedEnum;
use Gcego\Luminaries\Exceptions\LuminariesException;
use Gcego\Luminaries\IconName;
use Gcego\Luminaries\Luminaries;
use Gcego\Tables\Exceptions\InvalidTableException;
use Illuminate\Support\HtmlString;

/**
 * The icon API used by tables, infolists, layouts, navigation, pages, panels,
 * notifications, media and widgets, backed by gcego/luminaries.
 *
 * Names are any Luminaries identifier: semantic aliases (actions.edit),
 * canonical names (lucide:pencil) or bare Heroicons outline names (check).
 * A name that does not resolve renders nothing, so data can never inject
 * markup through an icon name. Applications may still add path icons at
 * boot: app(Icons::class)->register('school', 'M…').
 */
final class Icons
{
    public const string NamePattern = IconName::Pattern;

    public const string PathPattern = '/^[MmLlHhVvCcSsQqTtAaZz0-9 .,\-]{1,4000}$/';

    public function __construct(private readonly Luminaries $luminaries) {}

    public function register(string $name, string $path, bool $override = false): void
    {
        try {
            $this->luminaries->registerPath($name, $path, $override);
        } catch (LuminariesException) {
            throw InvalidTableException::invalid(sprintf('Icon "%s"', mb_substr($name, 0, 48)), $override ? 'use a lowercase name and SVG path data only.' : 'use a lowercase name and SVG path data only, and pass override: true to replace an existing icon.');
        }
    }

    public function has(string|BackedEnum $name): bool
    {
        return $this->luminaries->exists($name);
    }

    /**
     * Decorative SVG (aria-hidden): pair it with visible or screen-reader text.
     */
    public function svg(string|BackedEnum|null $name, string $class = 'size-4'): HtmlString
    {
        return $this->luminaries->svg($name, $class);
    }
}
