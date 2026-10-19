<?php

declare(strict_types=1);

namespace Gcego\Studio\Health\Checks;

use Gcego\Panels\PanelManager;
use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;
use Illuminate\Routing\Router;
use Throwable;

/**
 * Panel routes: no two panels share a path (on the same domain), no
 * application route shadows a panel's path, and every panel route keeps
 * its authentication middleware.
 */
final readonly class RoutesCheck implements StudioHealthCheck
{
    public function __construct(private PanelManager $panels, private Router $router) {}

    public function id(): string
    {
        return 'routes.panels';
    }

    public function production(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        $problems = [];
        $paths = [];

        try {
            foreach ($this->panels->names() as $name) {
                $report = $this->panels->inspect($name);
                $key = ($report->domain ?? '*').'|'.trim($report->path, '/');

                if (isset($paths[$key])) {
                    $problems[] = sprintf('panels "%s" and "%s" share the path "/%s".', $paths[$key], $name, trim($report->path, '/'));
                }

                $paths[$key] = $name;
            }
        } catch (Throwable $exception) {
            return HealthCheckResult::failure($this->id(), 'Routes', mb_substr($exception->getMessage(), 0, 300), 'Fix the panel definition named in the message.', 'gcego/panels');
        }

        $panelRoutes = 0;

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            $isPanel = str_starts_with($name, 'gcego.') && ! str_starts_with($name, 'gcego.media.') && ! str_starts_with($name, 'gcego.analytics.');

            if ($isPanel) {
                foreach ($paths as $key => $panel) {
                    [$domain, $path] = explode('|', $key, 2);

                    if (($route->getDomain() ?? '*') === $domain && ($route->uri() === $path || str_starts_with($route->uri(), $path.'/')) && ! $this->hasAuthentication($route->gatherMiddleware())) {
                        $problems[] = sprintf('the route "%s" of panel "%s" has no authentication middleware.', $name, $panel);
                    }
                }

                $panelRoutes++;

                continue;
            }

            foreach ($paths as $key => $panel) {
                [$domain, $path] = explode('|', $key, 2);

                if ($path !== '' && $route->uri() === $path && ($route->getDomain() ?? '*') === $domain && in_array('GET', $route->methods(), true)) {
                    $problems[] = sprintf('the application route "/%s" (%s) collides with panel "%s".', $route->uri(), $name === '' ? 'unnamed' : $name, $panel);
                }
            }
        }

        return $problems === []
            ? HealthCheckResult::pass($this->id(), 'Routes', sprintf('%d panels, %d panel routes, no collisions.', count($paths), $panelRoutes), 'gcego/panels')
            : HealthCheckResult::failure($this->id(), 'Routes', implode(' ', array_unique($problems)), 'Give each panel its own path (Panel::Path or Panel::Domain) and rename the colliding application routes.', 'gcego/panels');
    }

    /**
     * @param  array<array-key, mixed>  $middleware
     */
    private function hasAuthentication(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if (is_string($entry) && ($entry === 'auth' || str_starts_with($entry, 'auth:') || str_ends_with($entry, '\\Authenticate'))) {
                return true;
            }
        }

        return false;
    }
}
