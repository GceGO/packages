<?php

declare(strict_types=1);

namespace Gcego\Swatches\Palettes;

use Gcego\Swatches\Exceptions\DuplicateRegistrationException;
use Gcego\Swatches\Exceptions\InvalidPaletteException;

/**
 * Registered palettes: the Tailwind reference families plus application palettes.
 */
final class PaletteRegistry
{
    /**
     * @var array<string, Palette>
     */
    private array $palettes = [];

    public static function withDefaults(): self
    {
        $registry = new self;

        foreach (BuiltInPalettes::all() as $palette) {
            $registry->register($palette);
        }

        return $registry;
    }

    /**
     * @throws DuplicateRegistrationException
     */
    public function register(Palette $palette, bool $override = false): void
    {
        if (isset($this->palettes[$palette->name]) && ! $override) {
            throw DuplicateRegistrationException::duplicate('palette', $palette->name);
        }

        $this->palettes[$palette->name] = $palette;
    }

    public function has(string $name): bool
    {
        return isset($this->palettes[$name]);
    }

    /**
     * @throws InvalidPaletteException
     */
    public function get(string $name): Palette
    {
        return $this->palettes[$name] ?? throw InvalidPaletteException::unknown($name, $this->names());
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->palettes);
    }

    /**
     * @return array<string, Palette>
     */
    public function all(): array
    {
        return $this->palettes;
    }

    /**
     * Palettes registered by the application (not mirrored from Tailwind).
     *
     * @return array<string, Palette>
     */
    public function custom(): array
    {
        return array_filter($this->palettes, static fn (Palette $palette): bool => ! $palette->isBuiltIn());
    }
}
