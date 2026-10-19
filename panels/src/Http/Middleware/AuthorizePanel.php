<?php

declare(strict_types=1);

namespace Gcego\Panels\Http\Middleware;

use Closure;
use Gcego\Panels\Access\PanelAccess;
use Gcego\Panels\Context\PanelContext;
use Gcego\Panels\Context\PanelContextResolver;
use Gcego\Panels\Exceptions\PanelAccessDeniedException;
use Gcego\Panels\PanelsConfig;
use Gcego\Panels\Registry\PanelDefinitions;
use Gcego\Security\SecurityConfig;
use Gcego\Security\Tenancy\TenantContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator as RoutingUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * "gcego.panel:<name>": the last panel check of every panel route, after
 * Laravel's authentication (and verification and gcego/security tenancy).
 * It also runs as Livewire persistent middleware, so every Livewire update
 * of a component on a panel page is checked again against the panel of the
 * page it was mounted on (from Livewire's checksummed snapshot), never one
 * named by the browser.
 *
 * It selects the panel's guard, admits the user only if PanelAccess allows,
 * and publishes the PanelContext. The panel name is a route parameter set
 * by the panel's own route definition.
 */
final readonly class AuthorizePanel
{
    public const string Alias = 'gcego.panel';

    public function __construct(
        private PanelDefinitions $panels,
        private PanelAccess $access,
        private PanelContextResolver $contexts,
        private PanelsConfig $config,
        private Auth $auth,
        private TenantContext $tenants,
        private SecurityConfig $security,
        private UrlGenerator $urls,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $panel): Response
    {
        $descriptor = $this->panels->descriptor($panel);
        $guard = $descriptor->guard ?? $this->config->guard ?? $this->auth->getDefaultDriver();

        if ($descriptor->guard !== null || $this->config->guard !== null) {
            $this->auth->shouldUse($guard);
        }

        $user = $this->auth->guard($guard)->user();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.', [$guard]);
        }

        $context = new PanelContext($descriptor, $user, $guard, $this->tenants->tenant(), $descriptor->routeNamespace($this->config->namePrefix));

        if (! $this->access->allows($context)) {
            throw PanelAccessDeniedException::for($descriptor->name);
        }

        $this->contexts->set($context);

        if ($context->tenant !== null && $this->urls instanceof RoutingUrlGenerator) {
            $this->urls->defaults([$this->security->tenantParameter => (string) $context->tenant->key]);
        }

        return $next($request);
    }
}
