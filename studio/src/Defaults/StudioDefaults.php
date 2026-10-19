<?php

declare(strict_types=1);

namespace Gcego\Studio\Defaults;

use Gcego\Studio\Configuration\StudioConfiguration;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

/**
 * Framework-wide defaults, applied through the packages' own settings and
 * only where Studio's value is set (null keeps the package default):
 *
 * - appearance.default_layout / default_theme → gcego-panels.appearance;
 * - assets.vite → gcego-panels.assets.vite;
 * - discovery.{panels,modules,pages} = false → their gcego-registry
 *   discovery roots are removed (definitions registered in code still work).
 *
 * Panels may still override all of it per panel. Nothing here touches
 * authentication or authorization.
 */
final readonly class StudioDefaults
{
    public const array KindsByDiscovery = ['panels' => 'panel', 'modules' => 'module', 'pages' => 'page'];

    /** gcego/sentinel's account-state middleware alias (signs suspended or inactive accounts out). */
    public const string AccountMiddleware = 'sentinel.active';

    /** gcego/sentinel's required two-factor middleware alias (enrollment, and a session that proved the second factor). */
    public const string TwoFactorMiddleware = 'sentinel.two-factor';

    public function __construct(private Repository $config, private Application $app) {}

    public function applyToPanels(StudioConfiguration $studio): void
    {
        if ($studio->defaultLayout !== null) {
            $this->config->set('gcego-panels.appearance.default_layout', $studio->defaultLayout);
        }

        if ($studio->defaultTheme !== null) {
            $this->config->set('gcego-panels.appearance.default_theme', $studio->defaultTheme);
        }

        if ($studio->vite !== null) {
            $this->config->set('gcego-panels.assets.vite', $studio->vite);
        }

        $this->enforceAccountState();
    }

    /**
     * With gcego/sentinel installed, every panel request checks the account
     * state, so an account suspended mid-session loses panel access at once,
     * and enforces required two-factor authentication, so no panel can be
     * entered around it (Sentinel itself guards only its own pages). Never
     * removed by Studio.
     */
    private function enforceAccountState(): void
    {
        $provider = 'Gcego\\Sentinel\\SentinelServiceProvider';

        if (! class_exists($provider) || ! $this->app->providerIsLoaded($provider) || $this->config->get('gcego-sentinel.enabled', true) === false) {
            return;
        }

        $middleware = $this->config->get('gcego-panels.routing.middleware', ['web']);
        $middleware = is_array($middleware) ? array_values($middleware) : ['web'];

        foreach ([self::AccountMiddleware, self::TwoFactorMiddleware] as $required) {
            if (! in_array($required, $middleware, true)) {
                $middleware[] = $required;
            }
        }

        $this->config->set('gcego-panels.routing.middleware', $middleware);
    }

    public function applyToDiscovery(StudioConfiguration $studio): void
    {
        $disabled = array_keys(array_filter([
            'panel' => ! $studio->discoverPanels,
            'module' => ! $studio->discoverModules,
            'page' => ! $studio->discoverPages,
        ]));

        if ($disabled === []) {
            return;
        }

        $roots = $this->config->get('gcego-registry.discovery.roots', []);
        $roots = is_array($roots) ? $roots : [];
        $this->config->set('gcego-registry.discovery.roots', array_values(array_filter($roots, static fn (mixed $root): bool => ! is_array($root) || ! in_array($root['kind'] ?? null, $disabled, true))));
    }

    /**
     * The effective appearance (for diagnostics).
     *
     * @return array{layout: mixed, theme: mixed, vite: mixed}
     */
    public function effective(): array
    {
        return [
            'layout' => $this->config->get('gcego-panels.appearance.default_layout'),
            'theme' => $this->config->get('gcego-panels.appearance.default_theme'),
            'vite' => $this->config->get('gcego-panels.assets.vite'),
        ];
    }
}
