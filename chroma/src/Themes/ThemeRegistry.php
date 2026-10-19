<?php

declare(strict_types=1);

namespace Gcego\Chroma\Themes;

use Gcego\Chroma\Exceptions\InvalidThemeException;
use Gcego\Chroma\Exceptions\StyleConflictException;
use Gcego\Swatches\Themes\ThemeRegistry as ColorThemes;

/**
 * Registered styling themes. "gcego" (the CSS defaults) is always present, and every
 * gcego/swatches color theme is a known theme here too (with no styling overrides
 * unless one is registered under the same name).
 */
final class ThemeRegistry
{
    public const string Default = 'gcego';

    /**
     * @var array<string, Theme>
     */
    private array $themes = [];

    private int $version = 0;

    public function __construct(private readonly ?ColorThemes $colors = null)
    {
        $this->themes[self::Default] = new Theme(self::Default, label: 'GCEGO');
    }

    /**
     * @throws StyleConflictException
     */
    public function register(Theme $theme, bool $override = false): void
    {
        if (isset($this->themes[$theme->name]) && ! $override) {
            throw StyleConflictException::duplicate('theme', $theme->name);
        }

        $this->themes[$theme->name] = $theme;
        $this->version++;
    }

    public function has(string $name): bool
    {
        return isset($this->themes[$name]) || ($this->colors?->has($name) ?? false);
    }

    /**
     * @throws InvalidThemeException
     */
    public function get(string $name): Theme
    {
        if (isset($this->themes[$name])) {
            return $this->themes[$name];
        }

        if ($this->colors?->has($name) ?? false) {
            return $this->themes[$name] = new Theme($name, label: $this->colors->get($name)->label());
        }

        throw InvalidThemeException::unknown($name, $this->names());
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique([...array_keys($this->themes), ...($this->colors?->names() ?? [])]));
    }

    public function version(): int
    {
        return $this->version + ($this->colors?->version() ?? 0);
    }
}
