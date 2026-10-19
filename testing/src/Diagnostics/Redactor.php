<?php

declare(strict_types=1);

namespace Gcego\Testing\Diagnostics;

use Stringable;

/**
 * Replaces values under sensitive keys before they reach failure output.
 *
 * Keys are matched case-insensitively against fnmatch patterns such as
 * "*password*". Nested arrays and objects with toArray() are walked.
 */
final readonly class Redactor
{
    public const Mask = '[redacted]';

    /**
     * @param  list<string>  $patterns
     */
    public function __construct(private array $patterns = ['*password*', '*secret*', '*token*', '*api_key*', '*apikey*', '*authorization*', '*cookie*', '*credential*', '*private_key*', 'remember_token', 'two_factor_*']) {}

    public function isSensitive(string|int $key): bool
    {
        $key = strtolower((string) $key);

        foreach ($this->patterns as $pattern) {
            if (fnmatch(strtolower($pattern), $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public function redact(array $values, int $depth = 0): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            $redacted[$key] = $this->isSensitive($key) && $value !== null ? self::Mask : $this->value($value, $depth + 1);
        }

        return $redacted;
    }

    public function value(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 8) {
            return '…';
        }

        if (is_array($value)) {
            return $this->redact($value, $depth);
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            $array = $value->toArray();

            return is_array($array) ? $this->redact($array, $depth) : $value::class;
        }

        return $value;
    }

    /**
     * Masks every occurrence of the given secret strings inside a text.
     *
     * @param  list<string>  $secrets
     */
    public function scrub(string|Stringable $text, array $secrets): string
    {
        $text = (string) $text;

        foreach ($secrets as $secret) {
            if ($secret !== '') {
                $text = str_replace($secret, self::Mask, $text);
            }
        }

        return $text;
    }
}
