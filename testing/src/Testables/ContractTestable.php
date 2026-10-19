<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Contracts\RendererInterface;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\PermissionProvider;
use Gcego\Security\Contracts\TenantResolver;
use Gcego\Testing\Assertions\Verifier;
use Illuminate\Contracts\Auth\Authenticatable;
use JsonException;

/**
 * Behaviour every implementation of a GCEGO extension contract must have,
 * for extension authors to check their own classes against:
 *
 *   GcegoTest::contracts()->assertTenantResolverContract(new InstitutionResolver, '1', $member, $outsider);
 */
final class ContractTestable extends Testable
{
    private string $contract = '';

    public function __construct(Verifier $verifier)
    {
        parent::__construct($verifier);
    }

    /**
     * A component reports a type, validates and normalizes the same way
     * every time, and normalizes to plain JSON-encodable data.
     */
    public function assertComponentContract(ComponentInterface $component): self
    {
        $this->contract = 'ComponentInterface ('.$component::class.')';
        $definition = $component::definition();
        $this->check($definition->type !== '' && $definition->type === $component->type(), 'definition()', 'Expected definition()->type to be the component type.', ['type' => $component->type()], ['definition type' => $definition->type]);
        $this->check(count($component->validate()) === count($component->validate()), 'validate()', 'Expected validate() to be deterministic.');
        $first = $component->normalize();
        $this->check($first === $component->normalize(), 'normalize()', 'Expected normalize() to return the same array every time.');

        try {
            json_encode($first, JSON_THROW_ON_ERROR);
            $encodable = true;
        } catch (JsonException) {
            $encodable = false;
        }

        return $this->check($encodable, 'normalize()', 'Expected the normalized component to be JSON-encodable.', ['encodable' => true], ['encodable' => false]);
    }

    /**
     * A renderer returns the same HTML for the same component and context,
     * and never throws for a valid component.
     */
    public function assertRendererContract(RendererInterface $renderer, ComponentInterface $component): self
    {
        $this->contract = 'RendererInterface ('.$renderer::class.')';
        $html = null;
        $exception = $this->caught(function () use ($renderer, $component, &$html): void {
            $context = RenderContext::root($component);
            $html = [$renderer->render($component, $context)->toHtml(), $renderer->render($component, $context)->toHtml()];
        });

        return $this->check($exception === null && is_array($html) && $html[0] === $html[1], 'render()', 'Expected the renderer to render the component deterministically.', ['deterministic' => true], ['exception' => $this->describe($exception), 'differs' => is_array($html) && $html[0] !== $html[1]]);
    }

    /**
     * A styling resolver returns a class string, the same every time.
     */
    public function assertStylingResolverContract(StylingResolver $resolver, ComponentInterface $component, string $slot = 'root'): self
    {
        $this->contract = 'StylingResolver ('.$resolver::class.')';
        $context = RenderContext::root($component);
        $first = $resolver->classes($component, $slot, $context);

        return $this->check($first === $resolver->classes($component, $slot, $context), 'classes()', 'Expected the resolver to return the same classes every time.', ['classes' => $first]);
    }

    /**
     * A tenant resolver finds the tenant by key, returns null for unknown
     * keys, grants a membership to the member (for that tenant and that
     * user) and none to the outsider.
     */
    public function assertTenantResolverContract(TenantResolver $resolver, string $tenantKey, Authenticatable $member, Authenticatable $outsider, string $unknownKey = '999999999'): self
    {
        $this->contract = 'TenantResolver ('.$resolver::class.')';
        $tenant = $resolver->find($tenantKey);
        $this->check($tenant !== null && (string) $tenant->key === $tenantKey, 'find()', sprintf('Expected find("%s") to return that tenant.', $tenantKey), ['key' => $tenantKey], ['key' => $tenant?->key]);
        $this->check($resolver->find($unknownKey) === null, 'find()', sprintf('Expected find("%s") to return null for an unknown key.', $unknownKey), ['tenant' => null], ['tenant' => $resolver->find($unknownKey)?->id()]);

        if ($tenant === null) {
            return $this;
        }

        $membership = $resolver->membership($member, $tenant);
        $this->check($membership !== null && $membership->tenant->is($tenant) && (string) $membership->userKey === $this->identifier($member), 'membership()', 'Expected a membership for the member, in that tenant, for that user.', ['tenant' => $tenant->id(), 'user' => $member->getAuthIdentifier()], ['tenant' => $membership?->tenant->id(), 'user' => $membership?->userKey]);

        return $this->check($resolver->membership($outsider, $tenant) === null, 'membership()', 'Expected no membership for a user outside the tenant.', ['membership' => null], ['membership' => 'granted']);
    }

    /**
     * A permission provider grants what it grants, and answers false or
     * null (undecided) for anything else; never true by default.
     */
    public function assertPermissionProviderContract(PermissionProvider $provider, Authenticatable $user, string $granted, string $denied, AuthorizationContext $context): self
    {
        $this->contract = 'PermissionProvider ('.$provider::class.')';
        $this->check($provider->allows($user, $granted, $context) === true, 'allows()', sprintf('Expected "%s" to be granted.', $granted), ['allows' => true], ['allows' => $provider->allows($user, $granted, $context)]);

        return $this->check($provider->allows($user, $denied, $context) !== true, 'allows()', sprintf('Expected "%s" not to be granted.', $denied), ['allows' => 'false|null'], ['allows' => true]);
    }

    private function identifier(Authenticatable $user): string
    {
        $key = $user->getAuthIdentifier();

        return is_scalar($key) ? (string) $key : '';
    }

    protected function subject(): string
    {
        return 'contract';
    }

    protected function context(): array
    {
        return ['Contract' => $this->contract];
    }
}
