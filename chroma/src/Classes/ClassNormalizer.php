<?php

declare(strict_types=1);

namespace Gcego\Chroma\Classes;

use Gcego\Chroma\Exceptions\InvalidClassException;

/**
 * Turns class strings and lists into an ordered, de-duplicated list of tokens.
 *
 * Splitting happens on whitespace outside [] and () only, so arbitrary values
 * (`grid-cols-[1fr_auto]`, `[mask-type:luminance]`) are never cut apart.
 * The first occurrence of an exact duplicate wins; order is otherwise kept.
 */
final class ClassNormalizer
{
    public function __construct(private readonly ClassValidator $validator = new ClassValidator) {}

    /**
     * @param  string|iterable<mixed>  $classes
     * @return list<string>
     *
     * @throws InvalidClassException
     */
    public function normalize(string|iterable $classes): array
    {
        $tokens = [];

        foreach (is_string($classes) ? [$classes] : $classes as $entry) {
            if (! is_string($entry)) {
                throw InvalidClassException::type(get_debug_type($entry));
            }

            foreach ($this->split($entry) as $token) {
                $this->validator->validate($token);
                $tokens[$token] = true;
            }
        }

        return array_map('strval', array_keys($tokens));
    }

    /**
     * @return list<string>
     */
    public function split(string $classes): array
    {
        $tokens = [];
        $current = '';
        $depth = 0;

        foreach (str_split($classes) as $character) {
            if ($character === '[' || $character === '(') {
                $depth++;
            } elseif (($character === ']' || $character === ')') && $depth > 0) {
                $depth--;
            }

            if ($depth === 0 && ctype_space($character)) {
                if ($current !== '') {
                    $tokens[] = $current;
                }

                $current = '';

                continue;
            }

            $current .= $character;
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }
}
