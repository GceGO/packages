<?php

declare(strict_types=1);

namespace Gcego\Testing\Livewire;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use JsonException;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

/**
 * Real Livewire update requests: a POST to Livewire's update endpoint with
 * a snapshot taken from rendered HTML, through the HTTP kernel, so
 * persistent middleware (tenant resolution, panel access), checksums,
 * locked properties and hydration all run as they do in a browser.
 *
 * Livewire::test() is the faster choice for component behaviour; use this
 * to prove the server refuses forged or stale requests.
 */
final readonly class LivewireRequests
{
    public function __construct(private Container $container) {}

    /**
     * The decoded wire:snapshot of the first component named $name.
     */
    public static function snapshot(string $html, string $name): string
    {
        return self::snapshots($html, $name)[0] ?? '';
    }

    /**
     * @return list<string>
     */
    public static function snapshots(string $html, string $name): array
    {
        preg_match_all('/wire:snapshot="([^"]+)"[^>]*wire:name="'.preg_quote($name, '/').'"/', $html, $matches);

        return array_values(array_map(static fn (string $snapshot): string => html_entity_decode($snapshot, ENT_QUOTES), $matches[1]));
    }

    /**
     * Changes the snapshot's data without re-signing it: what an attacker
     * editing the page would send. Livewire must reject it.
     *
     * @param  array<string, mixed>|Closure(array<string, mixed>): array<string, mixed>  $changes
     */
    public static function forge(string $snapshot, array|Closure $changes): string
    {
        try {
            $decoded = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $snapshot;
        }

        if (! is_array($decoded) || ! is_array($decoded['data'] ?? null)) {
            return $snapshot;
        }

        /** @var array<string, mixed> $data */
        $data = $decoded['data'];
        $decoded['data'] = $changes instanceof Closure ? $changes($data) : array_replace($data, $changes);

        return (string) json_encode($decoded);
    }

    /**
     * Posts one component update as the signed-in user, through the
     * application's HTTP kernel (all middleware runs).
     *
     * @param  list<array{method: string, params?: list<mixed>}>  $calls
     * @param  array<string, mixed>  $updates
     * @param  array<string, mixed>  $session  e.g. the tenant session key.
     * @return TestResponse<Response>
     */
    public function update(string $snapshot, array $calls = [], array $updates = [], array $session = []): TestResponse
    {
        $livewire = $this->container->make('livewire');

        if (is_object($livewire) && method_exists($livewire, 'flushState')) {
            $livewire->flushState();
        }

        if (method_exists($this->container, 'forgetScopedInstances')) {
            $this->container->forgetScopedInstances();
        }

        if ($session !== []) {
            $store = $this->container->make('session.store');

            foreach ($session as $key => $value) {
                $store->put($key, $value);
            }
        }

        $uri = $this->container->make(HandleRequests::class)->getUpdateUri();
        $payload = json_encode(['components' => [[
            'snapshot' => $snapshot,
            'updates' => (object) $updates,
            'calls' => array_map(static fn (array $call): array => ['path' => '', 'params' => [], ...$call], $calls),
        ]]]);
        $request = Request::create(is_string($uri) ? $uri : '/livewire/update', 'POST', server: ['HTTP_X_LIVEWIRE' => '1', 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: $payload === false ? '{}' : $payload);
        $kernel = $this->container->make(Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return TestResponse::fromBaseResponse($response, $request);
    }

    /**
     * @return TestResponse<Response>
     */
    public function call(string $snapshot, string $method, mixed ...$params): TestResponse
    {
        return $this->update($snapshot, [['method' => $method, 'params' => array_values($params)]]);
    }
}
