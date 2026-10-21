<?php

declare(strict_types=1);

namespace Gcego\Core\Diagnostics;

use Throwable;

/** Last-line redaction for recognizable credentials; checks must still return metadata only. */
final class DiagnosticRedactor
{
    public static function text(string $value): string
    {
        $value = function_exists('mb_scrub') ? mb_scrub($value, 'UTF-8') : (preg_replace('/[^\x20-\x7E\r\n\t]/', '?', $value) ?? '[redacted]');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '[redacted]';
        $value = preg_replace('/-----BEGIN [^-]*PRIVATE KEY-----.*?-----END [^-]*PRIVATE KEY-----/s', '[redacted]', $value) ?? '[redacted]';
        $value = preg_replace('~(https?://)[^\s/@]+:[^\s/@]+@~i', '$1[redacted]@', $value) ?? '[redacted]';
        $value = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9+\/_=.\-]+/i', '$1 [redacted]', $value) ?? '[redacted]';
        $value = preg_replace('/\b((?:[a-z][a-z0-9_]*[_-])?(?:password|passwd|secret|token|api[_-]?key|authorization|cookie|credentials?))\b([\"\']?\s*[=:]\s*)("[^"]*"|\'[^\']*\'|[^\s,;]+)/i', '$1$2[redacted]', $value) ?? '[redacted]';

        return function_exists('mb_strcut') ? mb_strcut($value, 0, 4096, 'UTF-8') : substr($value, 0, 4096);
    }

    private static function scalar(mixed $value): string|int|float|bool
    {
        if (is_string($value)) {
            return self::text($value);
        }
        if (is_float($value) && ! is_finite($value)) {
            return '[non-finite number]';
        }

        return is_scalar($value) ? $value : '[redacted]';
    }

    public static function className(string $class): string
    {
        return str_contains($class, '@anonymous') ? 'anonymous class' : self::text($class);
    }

    public static function exceptionClass(Throwable $exception): string
    {
        return str_contains($exception::class, '@anonymous') ? 'anonymous exception' : self::text($exception::class);
    }

    /** @param array<string, scalar|list<scalar>|null> $details
     * @return array<string, scalar|list<scalar>|null>
     */
    public static function details(array $details): array
    {
        $safe = [];
        foreach ($details as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            if (is_object($value) || is_resource($value)) {
                $safe[self::text($key)] = '[redacted]';

                continue;
            }
            if (is_array($value) && (! array_is_list($value) || count(array_filter($value, static fn ($item): bool => ! is_scalar($item))) > 0)) {
                $safe[self::text($key)] = '[redacted]';

                continue;
            }
            if (preg_match('/(password|passwd|secret|token|api[_-]?key|authorization|cookie|credentials?)/i', $key)) {
                $safe[self::text($key)] = '[redacted]';

                continue;
            }
            $safe[self::text($key)] = is_array($value) ? array_map(self::scalar(...), $value) : ($value === null ? null : self::scalar($value));
        }

        return $safe;
    }
}
