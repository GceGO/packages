<?php

declare(strict_types=1);

namespace Gcego\Testing\Concerns;

use Gcego\Testing\GcegoTest;
use Gcego\Testing\Livewire\LivewireRequests;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;

trait InteractsWithGcegoLivewire
{
    /**
     * Livewire::test() for the component.
     *
     * @param  array<string, mixed>  $parameters
     * @return Testable<Component>
     */
    protected function gcegoLivewire(string $component, array $parameters = []): Testable
    {
        return GcegoTest::livewire($component, $parameters)->test();
    }

    protected function gcegoSnapshot(string $html, string $name): string
    {
        return LivewireRequests::snapshot($html, $name);
    }

    /**
     * A real Livewire update request through the HTTP kernel.
     *
     * @param  list<array{method: string, params?: list<mixed>}>  $calls
     * @param  array<string, mixed>  $updates
     * @param  array<string, mixed>  $session
     * @return TestResponse<Response>
     */
    protected function gcegoLivewireUpdate(string $snapshot, array $calls = [], array $updates = [], array $session = []): TestResponse
    {
        return GcegoTest::livewireRequests()->update($snapshot, $calls, $updates, $session);
    }
}
