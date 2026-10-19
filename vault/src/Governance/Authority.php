<?php

declare(strict_types=1);

namespace Gcego\Vault\Governance;

use Gcego\Vault\Authorization\PermissionEvaluator;
use Gcego\Vault\Exceptions\VaultAuthorizationException;
use Gcego\Vault\Support\Context;
use Gcego\Vault\Tenancy\Scope;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Whether an actor holds a Vault permission where they act. In a tenant this
 * means holding it in that tenant *and* being a verified member there; a
 * global grant never carries authority into a tenant. Without an actor
 * nothing is held (system code uses the trusted model API instead).
 */
final readonly class Authority
{
    public function __construct(private PermissionEvaluator $evaluator, private Context $context) {}

    public function holds(?Authenticatable $actor, string $permission, string $tenant = Scope::Global): bool
    {
        if (! $actor instanceof Model) {
            return false;
        }

        if ($tenant !== Scope::Global && ! $this->context->isMember($actor, $tenant)) {
            return false;
        }

        return $this->evaluator->allows($actor, $permission, $tenant, $this->context->panel()) === true;
    }

    /**
     * @throws VaultAuthorizationException
     */
    public function authorize(?Authenticatable $actor, string $permission, string $tenant = Scope::Global): void
    {
        if (! $this->holds($actor, $permission, $tenant)) {
            throw VaultAuthorizationException::because('missing_authority');
        }
    }

    public function isSuper(?Authenticatable $actor): bool
    {
        return $actor instanceof Model && $this->evaluator->effective($actor, Scope::Global, $this->context->panel())->super;
    }
}
