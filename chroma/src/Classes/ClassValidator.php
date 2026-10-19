<?php

declare(strict_types=1);

namespace Gcego\Chroma\Classes;

use Gcego\Chroma\Exceptions\InvalidClassException;

/**
 * Structural checks for one class token, without a Tailwind allowlist.
 *
 * Accepts any Tailwind v4 syntax (variants, arbitrary values and properties,
 * important modifiers, custom utilities) but rejects tokens that could break
 * out of an HTML attribute or are clearly malformed. Classes are trusted,
 * developer-authored code; never feed request input through here.
 */
final class ClassValidator
{
    public const int MaxLength = 200;

    public function validate(string $class): void
    {
        if ($class === '') {
            throw InvalidClassException::malformed($class, 'it is empty.');
        }

        if (strlen($class) > self::MaxLength) {
            throw InvalidClassException::malformed($class, sprintf('it is longer than %d characters.', self::MaxLength));
        }

        // ">" stays allowed: arbitrary variants such as [&>*]:p-4 need it, and attribute escaping makes it safe.
        if (preg_match('/[\s"<`{}\\\\]|[\x00-\x1F\x7F]/', $class) === 1) {
            throw InvalidClassException::malformed($class, 'it contains whitespace, double quotes, "<", backticks, braces, backslashes or control characters.');
        }

        $depth = ['[' => 0, '(' => 0];

        foreach (str_split($class) as $character) {
            match ($character) {
                '[' => $depth['[']++,
                '(' => $depth['(']++,
                ']' => $depth['[']--,
                ')' => $depth['(']--,
                default => null,
            };

            if ($depth['['] < 0 || $depth['('] < 0) {
                throw InvalidClassException::malformed($class, 'it has an unbalanced closing bracket.');
            }
        }

        if ($depth['['] !== 0 || $depth['('] !== 0) {
            throw InvalidClassException::malformed($class, 'it has an unbalanced bracket.');
        }

        // Colons inside brackets belong to arbitrary values and variants ([&::-webkit-details-marker]:hidden).
        $outside = (string) preg_replace('/\[[^\[\]]*\]/', '[]', (string) preg_replace('/\[[^\[\]]*\]/', '[]', $class));

        if (str_starts_with($outside, ':') || str_ends_with($outside, ':') || str_contains($outside, '::')) {
            throw InvalidClassException::malformed($class, 'it has an empty variant.');
        }
    }
}
