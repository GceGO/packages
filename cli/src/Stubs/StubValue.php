<?php

declare(strict_types=1);

namespace Gcego\Cli\Stubs;

use Gcego\Cli\Exceptions\StubException;

/**
 * A value for a stub placeholder, typed by where it lands in PHP source:
 *
 *  - string(): text inside a single-quoted PHP string ('...'), escaped;
 *  - identifier(): a class or namespace name, validated, inserted bare;
 *  - code(): a fragment the generator composed from validated values
 *    (field lists, imports) — never user input verbatim.
 *
 * Nothing in a stub is evaluated: rendering is placeholder replacement.
 */
final readonly class StubValue
{
    private function __construct(public string $rendered) {}

    public static function string(string $value): self
    {
        if (preg_match('/[\x00-\x08\x0B-\x1F\x7F]/', $value) === 1) {
            throw StubException::because('value', 'contains control characters.');
        }

        return new self(str_replace(['\\', "'"], ['\\\\', "\\'"], $value));
    }

    public static function identifier(string $value): self
    {
        if ($value !== '' && preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $value) !== 1) {
            throw StubException::because('value', sprintf('"%s" is not a PHP identifier.', $value));
        }

        return new self($value);
    }

    public static function code(string $value): self
    {
        return new self($value);
    }
}
