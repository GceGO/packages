<?php

declare(strict_types=1);

use Gcego\Chroma\Slots\SlotResolver;
use Gcego\Components\Component;
use Gcego\Rendering\Renderers\ContainerRenderer;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Permissions\GatePermissionProvider;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantMembership;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Definitions;
use Gcego\Testing\Tests\Fixtures\Models\User;
use Gcego\Testing\Tests\Fixtures\TenantResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

it('verifies the component contract for definitions', function (): void {
    GcegoTest::contracts()
        ->assertComponentContract(Definitions::subjects())
        ->assertComponentContract(Definitions::review())
        ->assertComponentContract(new Component);
});

it('verifies renderers and styling resolvers', function (): void {
    GcegoTest::contracts()
        ->assertRendererContract(app(ContainerRenderer::class), new Component)
        ->assertStylingResolverContract(app(SlotResolver::class), new Component);
});

it('verifies the tenant resolver contract', function (): void {
    $world = world();

    GcegoTest::contracts()->assertTenantResolverContract(new TenantResolver, '1', $world['admin'], $world['south']);
});

it('catches a tenant resolver that lets everyone in', function (): void {
    $world = world();
    $leaky = new class implements Gcego\Security\Contracts\TenantResolver
    {
        public function find(string $key): ?Tenant
        {
            return new Tenant('institution', $key);
        }

        public function membership(Authenticatable $user, Tenant $tenant): ?TenantMembership
        {
            return new TenantMembership($tenant, AuthorizationContext::keyOf($user));
        }
    };

    expect(failure(fn () => GcegoTest::contracts()->assertTenantResolverContract($leaky, '1', $world['admin'], $world['south'])))->toContain('unknown key');
});

it('verifies a permission provider grants only what it grants', function (): void {
    $user = User::query()->create(['name' => 'Admin']);
    Gate::define('subjects.export', static fn (User $user): bool => $user->name === 'Admin');

    GcegoTest::contracts()->assertPermissionProviderContract(app(GatePermissionProvider::class), $user, 'subjects.export', 'subjects.destroy', AuthorizationContext::guest('web'));
});
