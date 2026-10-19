<?php

declare(strict_types=1);

namespace Gcego\Swatches\Tokens;

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Exceptions\InvalidColorException;
use Gcego\Swatches\Exceptions\InvalidTokenException;
use Gcego\Swatches\Support\Safe;

/**
 * A token's value: a literal Color, or a reference to another token written "{primary}".
 *
 * References emit var(--gcego-primary), so they follow theme and mode changes in the
 * browser; on the server they are resolved through a TokenSet.
 */
final readonly class TokenValue
{
    private function __construct(
        public ?Color $color,
        public ?string $reference,
    ) {}

    /**
     * @throws InvalidTokenException
     */
    public static function parse(string $token, string $value): self
    {
        $value = trim($value, " \t\n\r");

        if (preg_match('/^\{([a-z0-9-]+)\}$/', $value, $match) === 1) {
            if (! Safe::isIdentifier($match[1], 60)) {
                throw InvalidTokenException::invalidValue($token);
            }

            return new self(null, $match[1]);
        }

        try {
            return new self(Color::parse($value), null);
        } catch (InvalidColorException) {
            throw InvalidTokenException::invalidValue($token);
        }
    }

    public static function color(Color $color): self
    {
        return new self($color, null);
    }

    public static function reference(string $token): self
    {
        if (! Safe::isIdentifier($token, 60)) {
            throw InvalidTokenException::unknown($token);
        }

        return new self(null, $token);
    }

    public function isReference(): bool
    {
        return $this->reference !== null;
    }

    /**
     * The CSS value: the serialized color, or var(--gcego-<reference>).
     */
    public function toCss(): string
    {
        return $this->reference !== null ? 'var('.Token::property($this->reference).')' : $this->colorOrFail()->toCss();
    }

    /**
     * The authoring notation: "{primary}" or the serialized color.
     */
    public function toString(): string
    {
        return $this->reference !== null ? '{'.$this->reference.'}' : $this->colorOrFail()->toCss();
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    private function colorOrFail(): Color
    {
        return $this->color ?? Color::black();
    }
}
