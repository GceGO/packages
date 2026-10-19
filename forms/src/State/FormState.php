<?php

declare(strict_types=1);

namespace Gcego\Forms\State;

/**
 * An immutable view of form state (field name => value; repeaters hold
 * item key => item state). Built per request from server data or untrusted
 * submissions; never shared between users or requests.
 */
final readonly class FormState
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public function __construct(private array $data = []) {}

    public function get(string $path, mixed $default = null): mixed
    {
        $value = $this->data;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $path): bool
    {
        $missing = new \stdClass;

        return $this->get($path, $missing) !== $missing;
    }

    public function filled(string $path): bool
    {
        $value = $this->get($path);

        return ! ($value === null || $value === '' || $value === [] || $value === false);
    }

    public function with(string $path, mixed $value): self
    {
        return new self(self::set($this->data, explode('.', $path), $value));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $segments
     * @return array<array-key, mixed>
     */
    private static function set(array $data, array $segments, mixed $value): array
    {
        $segment = array_shift($segments);

        if ($segment === null) {
            return $data;
        }

        $data[$segment] = $segments === [] ? $value : self::set(is_array($data[$segment] ?? null) ? $data[$segment] : [], $segments, $value);

        return $data;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }
}
