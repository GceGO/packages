<?php

declare(strict_types=1);

namespace Gcego\Chroma\Tokens;

use Gcego\Chroma\Exceptions\InvalidTokenException;
use Gcego\Swatches\Tokens\SemanticTokens;

/**
 * One validated token value: `--gcego-<name>: <value>`.
 */
final readonly class DesignToken
{
    public function __construct(
        public string $name,
        public string $value,
    ) {
        if (SemanticTokens::has($name)) {
            throw InvalidTokenException::colorToken($name);
        }

        $type = DefaultTokens::type($name) ?? throw InvalidTokenException::unknown($name, DefaultTokens::names());

        if (! $type->accepts($value)) {
            throw InvalidTokenException::invalidValue($name, $type->value);
        }
    }

    public function property(): string
    {
        return '--gcego-'.$this->name;
    }

    public function declaration(): string
    {
        return $this->property().': '.$this->value.';';
    }
}
