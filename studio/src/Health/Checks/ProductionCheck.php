<?php

declare(strict_types=1);

namespace Gcego\Studio\Health\Checks;

use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Contracts\Foundation\CachesRoutes;

/**
 * One production readiness rule (gcego:studio:health --production): read
 * only; each says what to run or change, Studio never does it.
 */
final readonly class ProductionCheck implements StudioHealthCheck
{
    public const array Rules = ['config-cache', 'route-cache', 'view-cache', 'debug', 'https', 'session', 'autoload'];

    public function __construct(private string $rule, private Application $app, private Repository $config) {}

    public function id(): string
    {
        return 'production.'.$this->rule;
    }

    public function production(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        return match ($this->rule) {
            'config-cache' => $this->app instanceof CachesConfiguration && $this->app->configurationIsCached()
                ? HealthCheckResult::pass($this->id(), 'Configuration cache', 'The configuration is cached.')
                : HealthCheckResult::warning($this->id(), 'Configuration cache', 'The configuration is not cached.', 'Run php artisan config:cache during deployment.'),
            'route-cache' => $this->app instanceof CachesRoutes && $this->app->routesAreCached()
                ? HealthCheckResult::pass($this->id(), 'Route cache', 'The routes are cached.')
                : HealthCheckResult::warning($this->id(), 'Route cache', 'The routes are not cached.', 'Run php artisan route:cache during deployment.'),
            'view-cache' => $this->views(),
            'debug' => $this->config->get('app.debug') === true
                ? HealthCheckResult::failure($this->id(), 'Debug mode', 'APP_DEBUG is on: errors show stack traces and configuration to visitors.', 'Set APP_DEBUG=false in production.')
                : HealthCheckResult::pass($this->id(), 'Debug mode', 'Debug mode is off.'),
            'https' => $this->https(),
            'session' => $this->session(),
            'autoload' => $this->autoload(),
            default => HealthCheckResult::skipped($this->id(), 'Production', 'Unknown rule.'),
        };
    }

    private function views(): HealthCheckResult
    {
        $compiled = $this->config->get('view.compiled');
        $files = is_string($compiled) && is_dir($compiled) ? glob($compiled.'/*.php') : [];

        return is_array($files) && $files !== []
            ? HealthCheckResult::pass($this->id(), 'View cache', 'Compiled views exist.')
            : HealthCheckResult::warning($this->id(), 'View cache', 'No compiled views were found.', 'Run php artisan view:cache during deployment.');
    }

    private function https(): HealthCheckResult
    {
        $url = $this->config->get('app.url');

        return is_string($url) && str_starts_with($url, 'https://')
            ? HealthCheckResult::pass($this->id(), 'HTTPS', 'APP_URL uses HTTPS.')
            : HealthCheckResult::warning($this->id(), 'HTTPS', 'APP_URL does not use HTTPS.', 'Serve the application over HTTPS and set APP_URL=https://… (and trusted proxies when behind a load balancer).');
    }

    private function session(): HealthCheckResult
    {
        $problems = [];

        if ($this->config->get('session.driver') === 'array') {
            $problems[] = 'the "array" session driver keeps nobody signed in';
        }

        if ($this->config->get('session.http_only') !== true) {
            $problems[] = 'session.http_only should be true';
        }

        if (! in_array($this->config->get('session.same_site'), ['lax', 'strict'], true)) {
            $problems[] = 'session.same_site should be "lax" or "strict"';
        }

        $url = $this->config->get('app.url');

        if (is_string($url) && str_starts_with($url, 'https://') && $this->config->get('session.secure') !== true) {
            $problems[] = 'session.secure should be true over HTTPS (SESSION_SECURE_COOKIE=true)';
        }

        return $problems === []
            ? HealthCheckResult::pass($this->id(), 'Session security', 'Session cookies are HTTP-only and same-site.')
            : HealthCheckResult::warning($this->id(), 'Session security', ucfirst(implode('; ', $problems)).'.', 'Adjust config/session.php (SESSION_* environment variables).');
    }

    private function autoload(): HealthCheckResult
    {
        $classmap = $this->app->basePath('vendor/composer/autoload_classmap.php');
        $map = is_file($classmap) ? require $classmap : [];
        $optimized = is_array($map) && array_filter(array_keys($map), static fn (mixed $class): bool => is_string($class) && str_starts_with($class, 'App\\')) !== [];

        return $optimized
            ? HealthCheckResult::pass($this->id(), 'Optimized autoloader', 'Composer\'s class map includes the application.')
            : HealthCheckResult::warning($this->id(), 'Optimized autoloader', 'The Composer autoloader is not optimized.', 'Run composer install --no-dev --optimize-autoloader during deployment.');
    }
}
