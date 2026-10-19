<?php

declare(strict_types=1);

namespace Gcego\Swatches\Themes;

use Gcego\Swatches\Exceptions\InvalidThemeException;
use Gcego\Swatches\Support\Safe;
use Gcego\Swatches\Tokens\TokenSet;

/**
 * A main color a user may pick for themselves ("red"): the primary tokens, light and dark.
 * It applies over the theme while <html data-gcego-accent="red">, and to any element marked
 * data-gcego-accent-swatch="red" (so a swatch shows its own color with bg-primary).
 */
final readonly class Accent
{
    /** The tokens an accent may set. */
    public const array Tokens = ['primary', 'primary-hover', 'primary-foreground', 'primary-soft', 'ring'];

    public TokenSet $light;

    public TokenSet $dark;

    /**
     * @param  array<string, string>  $light
     * @param  array<string, string>  $dark
     *
     * @throws InvalidThemeException
     */
    public function __construct(public string $name, public string $label, array $light, array $dark)
    {
        if (! Safe::isIdentifier($name, 30)) {
            throw InvalidThemeException::invalidConfig('accents', sprintf('%s is not a valid accent name.', Safe::quote($name)));
        }

        foreach ([...array_keys($light), ...array_keys($dark)] as $token) {
            if (! in_array($token, self::Tokens, true)) {
                throw InvalidThemeException::invalidConfig('accents.'.$name, sprintf('%s is not one of %s.', Safe::quote($token), implode(', ', self::Tokens)));
            }
        }

        $this->light = new TokenSet($light);
        $this->dark = new TokenSet($dark);
    }
}
