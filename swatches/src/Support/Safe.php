<?php

declare(strict_types=1);

namespace Gcego\Swatches\Support;

/**
 * Quoting for untrusted input in exception messages and reports: printable ASCII
 * only, at most 40 characters, so a message never carries markup or control bytes.
 */
final class Safe
{
    public const string IdentifierPattern = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    public static function quote(string $value): string
    {
        $clean = (string) preg_replace('/[^\x20-\x7E]|[<>"\'`\\\\]/', '?', $value);

        return '"'.(strlen($clean) > 40 ? substr($clean, 0, 40).'…' : $clean).'"';
    }

    public static function isIdentifier(string $value, int $maxLength = 50): bool
    {
        return strlen($value) <= $maxLength && preg_match(self::IdentifierPattern, $value) === 1;
    }
}
