<?php

declare(strict_types=1);

namespace Gcego\Testing\Livewire;

use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Testables\Testable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Testing\TestResponse;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable as LivewireTestable;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mounts a Livewire component with Livewire's own testing API, as the
 * user and tenant you choose. test() returns Livewire's Testable, so every
 * Livewire assertion (assertSee, assertSet, assertHasErrors,
 * assertDispatched, assertRedirect, assertForbidden…) is available as is.
 *
 * The extra assertions here are the security ones the GCEGO hosts rely on.
 */
final class LivewireTestAdapter extends Testable
{
    /**
     * @param  class-string|string  $component
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(Verifier $verifier, private readonly TestEnvironment $environment, private readonly string $component, private readonly array $parameters = [])
    {
        parent::__construct($verifier);
    }

    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $tenant, $guard);

        return $this;
    }

    /**
     * @return LivewireTestable<Component>
     */
    public function test(): LivewireTestable
    {
        return Livewire::test($this->component, $this->parameters);
    }

    /**
     * Shortcut for test()->assertSee().
     *
     * @return LivewireTestable<Component>
     */
    public function assertSee(string $text): LivewireTestable
    {
        return $this->test()->assertSee($text);
    }

    /**
     * Mounting is refused with this HTTP status (403 for a denied user,
     * 404 for a record outside the tenant), using Livewire's assertStatus.
     */
    public function assertMountRefused(int $status): self
    {
        $this->test()->assertStatus($status);

        return $this;
    }

    /**
     * Setting the property from the client is refused (#[Locked]).
     */
    public function assertLocked(string $property, mixed $value): self
    {
        $exception = $this->caught(fn () => $this->test()->set($property, $value));

        return $this->check($this->verifier->rootOf($exception, CannotUpdateLockedPropertyException::class) !== null, sprintf('assertLocked(%s)', $this->export($property)), sprintf('Expected the property [%s] to be locked.', $property), ['exception' => CannotUpdateLockedPropertyException::class], ['exception' => $this->describe($exception)]);
    }

    /**
     * A real update with a forged snapshot (or any other tampered request)
     * is refused: the response is an error, not a re-render.
     *
     * @param  TestResponse<\Illuminate\Http\Response>  $response
     */
    public function assertRequestRefused(TestResponse $response): self
    {
        $status = $response->getStatusCode();

        return $this->check($status >= 400, 'assertRequestRefused()', 'Expected the Livewire request to be refused.', ['status' => '≥ 400'], ['status' => $status]);
    }

    protected function subject(): string
    {
        return 'livewire';
    }

    protected function context(): array
    {
        $user = $this->environment->user();

        return ['Component' => $this->component, 'User' => $user === null ? 'guest' : $user->getAuthIdentifier(), 'Tenant' => $this->environment->currentTenant() ?? '(none)'];
    }
}
