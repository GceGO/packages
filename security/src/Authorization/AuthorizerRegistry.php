<?php

declare(strict_types=1);

namespace Gcego\Security\Authorization;

use Gcego\Security\Contracts\Authorizer;
use Gcego\Security\Contracts\ScopeProvider;

/**
 * Application-lifetime registrations: custom Authorizers and ScopeProviders.
 * Registered in service providers; they hold no request state.
 */
final class AuthorizerRegistry
{
    /**
     * @var list<Authorizer>
     */
    private array $authorizers = [];

    /**
     * @var list<ScopeProvider>
     */
    private array $scopes = [];

    public function authorizer(Authorizer $authorizer): void
    {
        $this->authorizers[] = $authorizer;
    }

    public function scope(ScopeProvider $provider): void
    {
        $this->scopes[] = $provider;
    }

    /**
     * @return list<Authorizer>
     */
    public function authorizers(): array
    {
        return $this->authorizers;
    }

    /**
     * @return list<ScopeProvider>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }
}
