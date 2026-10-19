<?php

declare(strict_types=1);

namespace Gcego\Swatches\Themes;

use Gcego\Swatches\Exceptions\InvalidThemeException;
use Gcego\Swatches\Exceptions\InvalidTokenException;
use Gcego\Swatches\Support\Safe;
use Gcego\Swatches\Tokens\TokenSet;

/**
 * A named color theme: token overrides for light and dark mode on top of a parent theme.
 *
 *     new Theme('campus', extends: 'ocean',
 *         light: ['primary' => '#1d4ed8', 'primary-hover' => '#1e40af'],
 *         dark: ['primary' => '#60a5fa'],
 *     );
 *
 * Tokens left out are inherited. A light override does not leak into dark mode.
 * Only "gcego" (the catalog defaults) has no parent.
 */
final readonly class Theme
{
    public const string Base = 'gcego';

    public TokenSet $light;

    public TokenSet $dark;

    /**
     * @param  array<mixed>|TokenSet  $light
     * @param  array<mixed>|TokenSet  $dark
     *
     * @throws InvalidThemeException|InvalidTokenException
     */
    public function __construct(
        public string $name,
        array|TokenSet $light = [],
        array|TokenSet $dark = [],
        public ?string $extends = self::Base,
        public ?string $label = null,
        public ?string $description = null,
        public ThemeSource $source = ThemeSource::Runtime,
    ) {
        if (! Safe::isIdentifier($name)) {
            throw InvalidThemeException::invalidName($name);
        }

        if ($extends !== null && ! Safe::isIdentifier($extends)) {
            throw InvalidThemeException::invalidName($extends);
        }

        if ($extends === $name) {
            throw InvalidThemeException::circular([$name, $name]);
        }

        if ($label !== null && (strlen($label) > 60 || preg_match('/[<>]/', $label) === 1)) {
            throw InvalidThemeException::invalidConfig('themes.'.$name.'.label', 'must be plain text up to 60 characters.');
        }

        $this->light = $light instanceof TokenSet ? $light : new TokenSet($light);
        $this->dark = $dark instanceof TokenSet ? $dark : new TokenSet($dark);
    }

    /**
     * @param  array<mixed>  $config  ['label' => …, 'extends' => …, 'light' => [...], 'dark' => [...]] ("tokens" is accepted for "light")
     *
     * @throws InvalidThemeException|InvalidTokenException
     */
    public static function fromConfig(string $name, array $config, ThemeSource $source = ThemeSource::Config): self
    {
        $unknown = array_diff(array_keys($config), ['label', 'description', 'extends', 'light', 'tokens', 'dark']);

        if ($unknown !== []) {
            throw InvalidThemeException::invalidConfig('themes.'.$name, 'only "label", "description", "extends", "light" and "dark" are allowed.');
        }

        $light = $config['light'] ?? $config['tokens'] ?? [];
        $dark = $config['dark'] ?? [];
        $extends = array_key_exists('extends', $config) ? $config['extends'] : self::Base;
        $label = $config['label'] ?? null;
        $description = $config['description'] ?? null;

        if (! is_array($light) || ! is_array($dark)) {
            throw InvalidThemeException::invalidConfig('themes.'.$name, '"light" and "dark" must map token names to colors.');
        }

        if (($extends !== null && ! is_string($extends)) || ($label !== null && ! is_string($label)) || ($description !== null && ! is_string($description))) {
            throw InvalidThemeException::invalidConfig('themes.'.$name, '"extends", "label" and "description" must be strings.');
        }

        return new self($name, $light, $dark, $name === self::Base ? null : $extends, $label, $description, $source);
    }

    public function label(): string
    {
        return $this->label ?? ucfirst(str_replace('-', ' ', $this->name));
    }

    public function isBase(): bool
    {
        return $this->extends === null;
    }

    /**
     * @return array{name: string, label: string, extends: string|null, description: string|null, source: string, light: array<string, string>, dark: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label(),
            'extends' => $this->extends,
            'description' => $this->description,
            'source' => $this->source->value,
            'light' => $this->light->toArray(),
            'dark' => $this->dark->toArray(),
        ];
    }
}
