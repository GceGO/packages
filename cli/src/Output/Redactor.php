<?php

declare(strict_types=1);

namespace Gcego\Cli\Output;

/**
 * Masks secrets in anything a command prints: values of keys that look
 * sensitive (passwords, secrets, tokens, keys, credentials, DSNs) become
 * "[redacted]", and absolute paths become relative to the application.
 */
final readonly class Redactor
{
    private const string SensitiveKey = '/(password|passwd|secret|token|api[_-]?key|private[_-]?key|^key$|app[_-]?key|credential|dsn|signature|salt|cookie)/i';

    public function __construct(private string $basePath) {}

    public function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match(self::SensitiveKey, $key) === 1 && $value !== null && $value !== '' && ! is_bool($value) && ! is_array($value)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            $result = [];

            foreach ($value as $name => $item) {
                $result[$name] = $this->redact($item, is_string($name) ? $name : $key);
            }

            return $result;
        }

        if (is_string($value)) {
            return str_replace(rtrim($this->basePath, '/').'/', '', $value);
        }

        return $value;
    }
}
