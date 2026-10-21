<?php

declare(strict_types=1);

namespace Gcego\Components\Evaluation;

use Closure;
use Illuminate\Support\Arr;

/**
 * The mutable runtime state that Get and Set read and write with dot paths.
 *
 * Definitions stay immutable; state is the only part of evaluation that
 * changes, and it is owned by the caller that supplies it. Reads and writes
 * made inside record() are reported, so dependencies need no declaration.
 */
final class State
{
    /**
     * Open recordings, innermost last.
     *
     * @var list<array{reads: array<string, true>, writes: array<string, true>}>
     */
    private array $recordings = [];

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function __construct(private array $data = []) {}

    /**
     * Run the callback while recording every path it reads and writes,
     * which is how dependencies between components are discovered.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return array{result: TResult, reads: list<string>, writes: list<string>}
     */
    public function record(Closure $callback): array
    {
        $this->recordings[] = ['reads' => [], 'writes' => []];
        $key = array_key_last($this->recordings);

        try {
            $result = $callback();
        } finally {
            // Recordings nest, so the one opened here is always the last.
            $recording = $this->recordings[$key];
            array_pop($this->recordings);
        }

        return [
            'result' => $result,
            'reads' => array_map(strval(...), array_keys($recording['reads'])),
            'writes' => array_map(strval(...), array_keys($recording['writes'])),
        ];
    }

    /**
     * Read a value; an empty path returns the whole state.
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $this->note('reads', $path);

        return $path === '' ? $this->data : Arr::get($this->data, $path, $default);
    }

    public function set(string $path, mixed $value): void
    {
        $this->note('writes', $path);

        if ($path === '') {
            $this->data = is_array($value) ? $value : [$value];

            return;
        }

        Arr::set($this->data, $path, $value);
    }

    public function has(string $path): bool
    {
        return $path === '' || Arr::has($this->data, $path);
    }

    /**
     * Read without recording a dependency.
     */
    public function peek(string $path, mixed $default = null): mixed
    {
        return $path === '' ? $this->data : Arr::get($this->data, $path, $default);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * @param  'reads'|'writes'  $kind
     */
    private function note(string $kind, string $path): void
    {
        foreach (array_keys($this->recordings) as $index) {
            $this->recordings[$index][$kind][$path] = true;
        }
    }
}
