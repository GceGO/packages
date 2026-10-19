<?php

declare(strict_types=1);

namespace Gcego\Studio\Health\Checks;

use Gcego\Panels\PanelManager;
use Gcego\Studio\Configuration\StudioConfiguration;
use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Router;
use Throwable;

/**
 * Laravel authentication is configured for the panels: their guards
 * exist, the guard's user provider names a real model, and a "login"
 * route exists (gcego/sentinel or your own) for guests to be
 * sent to. Studio implements no authentication of its own.
 */
final readonly class AuthenticationCheck implements StudioHealthCheck
{
    public function __construct(private Repository $config, private PanelManager $panels, private Router $router, private StudioConfiguration $studio) {}

    public function id(): string
    {
        return 'security.authentication';
    }

    public function production(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        if (! $this->studio->requireAuthentication) {
            return HealthCheckResult::skipped($this->id(), 'Authentication', 'Skipped (gcego-studio.security.require_authentication is false); panels still require authentication.', 'gcego/panels');
        }

        $problems = [];
        $guards = [];

        try {
            foreach ($this->panels->names() as $name) {
                $guards[$this->panels->inspect($name)->guard] = $name;
            }
        } catch (Throwable) {
        }

        $default = $this->config->get('auth.defaults.guard');
        $guards[is_string($default) ? $default : 'web'] ??= 'default';

        foreach (array_keys($guards) as $guard) {
            $definition = $this->config->get('auth.guards.'.$guard);

            if (! is_array($definition)) {
                $problems[] = sprintf('the guard "%s" is not defined in config/auth.php.', $guard);

                continue;
            }

            $provider = $this->config->get('auth.providers.'.(is_string($definition['provider'] ?? null) ? $definition['provider'] : ''));
            $model = is_array($provider) ? ($provider['model'] ?? null) : null;

            if (is_array($provider) && ($provider['driver'] ?? null) === 'eloquent' && (! is_string($model) || ! class_exists($model))) {
                $problems[] = sprintf('the user provider of guard "%s" names no existing model.', $guard);
            }
        }

        $warnings = [];

        if (! $this->router->has('login')) {
            $warnings[] = 'No route is named "login", so guests cannot be redirected to sign in (install gcego/sentinel, or name your login route "login").';
        }

        if ($problems !== []) {
            return HealthCheckResult::failure($this->id(), 'Authentication', implode(' ', $problems), 'Define the guards and user providers in config/auth.php.', 'gcego/security');
        }

        return $warnings === []
            ? HealthCheckResult::pass($this->id(), 'Authentication', sprintf('Guards %s are configured and a login route exists%s.', implode(', ', array_keys($guards)), class_exists('Gcego\Sentinel\SentinelServiceProvider') ? ' (gcego/sentinel installed)' : ''), 'gcego/security')
            : HealthCheckResult::warning($this->id(), 'Authentication', implode(' ', $warnings), 'Add a login route.', 'gcego/security');
    }
}
