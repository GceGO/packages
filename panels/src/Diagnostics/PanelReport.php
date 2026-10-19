<?php

declare(strict_types=1);

namespace Gcego\Panels\Diagnostics;

/**
 * What a panel is made of, and what is wrong with it. Contains no user data.
 */
final readonly class PanelReport
{
    /**
     * @param  list<string>  $modules
     * @param  list<string>  $pages  Page identities routed in the panel.
     * @param  array<string, string>  $routes  Page identity => route name.
     * @param  list<string>  $middleware
     * @param  list<PanelDiagnostic>  $diagnostics
     */
    public function __construct(
        public string $identity,
        public string $title,
        public string $path,
        public ?string $domain,
        public string $routeNamespace,
        public string $guard,
        public ?string $tenant,
        public string $navigation,
        public ?string $dashboard,
        public array $modules,
        public array $pages,
        public array $routes,
        public array $middleware,
        public array $diagnostics,
    ) {}

    public function healthy(): bool
    {
        return array_filter($this->diagnostics, static fn (PanelDiagnostic $diagnostic): bool => $diagnostic->level === 'error') === [];
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map(static fn (PanelDiagnostic $diagnostic): string => $diagnostic->code, $this->diagnostics);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'identity' => $this->identity,
            'title' => $this->title,
            'path' => $this->path,
            'domain' => $this->domain,
            'route_namespace' => $this->routeNamespace,
            'guard' => $this->guard,
            'tenant' => $this->tenant,
            'navigation' => $this->navigation,
            'dashboard' => $this->dashboard,
            'modules' => $this->modules,
            'pages' => $this->pages,
            'routes' => $this->routes,
            'middleware' => $this->middleware,
            'healthy' => $this->healthy(),
            'diagnostics' => array_map(static fn (PanelDiagnostic $diagnostic): array => ['level' => $diagnostic->level, 'code' => $diagnostic->code, 'message' => $diagnostic->message], $this->diagnostics),
        ];
    }
}
