<?php

declare(strict_types=1);

namespace Gcego\Tables\Selection;

/**
 * Selected row keys from the browser: untrusted, bounded, de-duplicated.
 *
 * Keys are strings (integer, UUID and ULID keys all work) of at most 191
 * characters. A selection only says which rows a user ticked: every key is
 * re-resolved through the scoped source and re-authorized by gcego/actions
 * before anything runs, so a forged or cross-tenant key does nothing.
 */
final readonly class Selection
{
    /**
     * @param  list<string>  $keys
     */
    private function __construct(public array $keys, public bool $truncated) {}

    public static function from(mixed $keys, int $max): self
    {
        if (! is_array($keys)) {
            return new self([], false);
        }

        $clean = [];

        foreach ($keys as $key) {
            if ((is_string($key) || is_int($key)) && ($key = (string) $key) !== '' && strlen($key) <= 191 && preg_match('/^[A-Za-z0-9_\-:.]+$/', $key) === 1) {
                $clean[$key] = $key;
            }
        }

        $clean = array_values($clean);

        return new self(array_slice($clean, 0, $max), count($clean) > $max);
    }

    /**
     * The selection plus the given (visible page) keys, still bounded.
     *
     * @param  list<string>  $keys
     */
    public function with(array $keys, int $max): self
    {
        return self::from([...$this->keys, ...$keys], $max);
    }

    /**
     * @param  list<string>  $keys
     */
    public function without(array $keys): self
    {
        return new self(array_values(array_diff($this->keys, $keys)), false);
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->keys, true);
    }

    public function count(): int
    {
        return count($this->keys);
    }

    /**
     * @param  list<string>  $keys
     */
    public function containsAll(array $keys): bool
    {
        return $keys !== [] && array_diff($keys, $this->keys) === [];
    }
}
