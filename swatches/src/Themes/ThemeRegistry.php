<?php

declare(strict_types=1);

namespace Gcego\Swatches\Themes;

use Gcego\Swatches\Exceptions\DuplicateRegistrationException;
use Gcego\Swatches\Exceptions\InvalidThemeException;
use Gcego\Swatches\Tokens\SemanticTokens;

/**
 * Registered color themes. "gcego" (the catalog defaults) is always present.
 *
 * Holds application-wide registrations only. Per-request choices (a panel's
 * theme, a tenant's brand) are passed to the methods that need them.
 */
final class ThemeRegistry
{
    /**
     * @var array<string, Theme>
     */
    private array $themes = [];

    /**
     * @var array<string, ResolvedTheme>
     */
    private array $resolved = [];

    private int $version = 0;

    public function __construct()
    {
        $this->themes[Theme::Base] = new Theme(Theme::Base, extends: null, label: 'GCEGO Default', description: 'The GCEGO teal theme.', source: ThemeSource::BuiltIn);
    }

    public static function withDefaults(): self
    {
        $registry = new self;

        foreach (BuiltInThemes::all() as $theme) {
            $registry->register($theme);
        }

        return $registry;
    }

    /**
     * @throws DuplicateRegistrationException|InvalidThemeException
     */
    public function register(Theme $theme, bool $override = false): void
    {
        if (isset($this->themes[$theme->name]) && ! $override) {
            throw DuplicateRegistrationException::duplicate('theme', $theme->name);
        }

        if ($theme->name === Theme::Base && $theme->extends !== null) {
            throw InvalidThemeException::invalidConfig('themes.gcego', 'the base theme cannot extend another theme.');
        }

        $this->themes[$theme->name] = $theme;
        $this->resolved = [];
        $this->version++;
    }

    public function has(string $name): bool
    {
        return isset($this->themes[$name]);
    }

    /**
     * @throws InvalidThemeException
     */
    public function get(string $name): Theme
    {
        return $this->themes[$name] ?? throw InvalidThemeException::unknown($name, $this->names());
    }

    /**
     * The theme merged over its ancestors, base first.
     *
     * @throws InvalidThemeException unknown theme or parent, or circular inheritance
     */
    public function resolve(string $name): ResolvedTheme
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $chain = [];
        $current = $this->get($name);

        while (true) {
            if (in_array($current->name, $chain, true)) {
                throw InvalidThemeException::circular([...array_reverse($chain), $current->name]);
            }

            $chain[] = $current->name;

            if ($current->extends === null) {
                break;
            }

            if (! isset($this->themes[$current->extends])) {
                throw InvalidThemeException::unknownParent($current->name, $current->extends);
            }

            $current = $this->themes[$current->extends];
        }

        $chain = array_reverse($chain);
        $light = SemanticTokens::light();
        $dark = SemanticTokens::dark();

        foreach ($chain as $link) {
            $light = $light->with($this->themes[$link]->light);
            $dark = $dark->with($this->themes[$link]->dark);
        }

        return $this->resolved[$name] = new ResolvedTheme($this->themes[$name], $chain, $light, $dark);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->themes);
    }

    /**
     * @return array<string, Theme>
     */
    public function all(): array
    {
        return $this->themes;
    }

    public function version(): int
    {
        return $this->version;
    }
}
