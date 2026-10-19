<?php

declare(strict_types=1);

namespace Gcego\Pages\Authorization;

use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\Records\RecordAuthorizer;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Security\SecurityManager;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Page-level, child-level and record-level authorization, all decided by
 * gcego/security (request-scoped; decisions are memoized per request and
 * security fingerprint, so they never cross users, tenants or requests):
 *
 *  - Page: the Requirement nodes on the page (and above a child's path).
 *    A page without any follows gcego-pages.authorization.default (deny,
 *    authenticated or public).
 *  - Record: the page Source's scoped lookup (tenant and security scopes),
 *    its requirements, and the record ability ("view", "update") through the
 *    model's policy (RecordAuthorizer). Out of scope is always 404.
 *
 * A route existing never grants anything: every page request, and every
 * Livewire update of a page, goes through here again.
 */
final class PageAuthorizer
{
    /**
     * @var array<string, AuthorizationDecision>
     */
    private array $decisions = [];

    /**
     * @var array<string, array{0: AuthorizationDecision, 1: Model|null}>
     */
    private array $records = [];

    public function __construct(private readonly SecurityManager $security, private readonly RecordAuthorizer $recordAuthorizer, private readonly PagesConfig $config, private readonly Container $container) {}

    /**
     * The decision for the page (or for the component at $path in it).
     */
    public function decision(string $identity, Page $page, ?string $path = null): AuthorizationDecision
    {
        $key = $identity.'|'.$path.'|'.$this->scope();

        return $this->decisions[$key] ??= $this->decide($page, $path);
    }

    public function allows(string $identity, Page $page, ?string $path = null): bool
    {
        return $this->decision($identity, $page, $path)->allowed;
    }

    /**
     * @throws AuthenticationException for guests (Laravel sends them to the login page).
     * @throws AccessDeniedException (403, or 404 when concealed).
     */
    public function authorize(string $identity, Page $page): void
    {
        $decision = $this->decision($identity, $page);

        if ($decision->allowed) {
            return;
        }

        if ($this->security->context()->user === null && $decision->status !== 404) {
            throw new AuthenticationException;
        }

        $decision->authorize();
    }

    /**
     * Whether a child of the page may be shown. Children without requirements of their own share the page's decision.
     */
    public function allowsChild(string $identity, Page $page, string $path): bool
    {
        foreach ((new ComponentWalker)->preOrder($page) as $node) {
            if ($node->path->toString() !== $path) {
                continue;
            }

            foreach ($node->component->configurations() as $configuration) {
                if ($configuration instanceof Requirement) {
                    return $this->allows($identity, $page, $path);
                }
            }

            return true;
        }

        return false;
    }

    /**
     * The record behind a key, looked up through the page's Source and
     * authorized for the page's record ability (or $ability).
     *
     * @return array{0: AuthorizationDecision, 1: Model|null} The decision and, when allowed, the record.
     */
    public function record(string $identity, Page $page, string $key, ?string $ability = null): array
    {
        $ability ??= $page->recordAbility();
        $memo = $identity.'|'.$key.'|'.$ability.'|'.$this->scope();

        if (isset($this->records[$memo])) {
            return $this->records[$memo];
        }

        $source = $page->source();

        if ($source === null) {
            return $this->records[$memo] = [AuthorizationDecision::deny($ability, DenialReason::NotFound, null, 404), null];
        }

        [$decision, $record] = $this->recordAuthorizer->inspect($source, $key, $ability);

        if ($decision->allowed && ! $record instanceof Model) {
            $decision = AuthorizationDecision::deny($ability, DenialReason::NotFound, null, 404);
        }

        return $this->records[$memo] = [$decision, $decision->allowed && $record instanceof Model ? $record : null];
    }

    /**
     * Decisions hold for one principal (fingerprint: user, guard, tenant) in one request.
     */
    private function scope(): string
    {
        return $this->security->context()->fingerprint().'|'.($this->container->bound('request') ? spl_object_id($this->container->make('request')) : 0);
    }

    private function decide(Page $page, ?string $path): AuthorizationDecision
    {
        $decision = $this->security->definition($page, $path);

        if ($decision->allowed || $decision->reason !== DenialReason::NoRequirement) {
            return $decision;
        }

        return match ($this->config->defaultAccess) {
            'public' => AuthorizationDecision::allow('access', null, ['default' => 'public']),
            'authenticated' => $this->security->check(new Authenticated),
            default => $decision,
        };
    }
}
