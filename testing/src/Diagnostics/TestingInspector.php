<?php

declare(strict_types=1);

namespace Gcego\Testing\Diagnostics;

use Composer\InstalledVersions;
use Gcego\Testing\Accessibility\BrowserAccessibility;
use Gcego\Testing\Compatibility\CompatibilityResult;
use Gcego\Testing\Compatibility\PackageCompatibility;
use Gcego\Testing\TestingConfig;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

/**
 * Reports whether the test environment is usable and isolated: testing
 * dependencies, database, cache and queue drivers, registry caching,
 * tenancy, fixtures, parallel testing, browser tools and the GCEGO
 * packages. Read-only: it changes nothing.
 */
final readonly class TestingInspector
{
    public function __construct(private Container $container, private TestingConfig $config) {}

    public function inspect(): TestingReport
    {
        $packages = (new PackageCompatibility($this->container instanceof Application ? $this->container : null))->check();
        $diagnostics = [
            ...$this->dependencies(),
            $this->database(),
            ...$this->drivers(),
            $this->registryCache(),
            $this->tenancy(),
            $this->fixtures(),
            $this->parallel(),
            $this->browser(),
        ];

        foreach ($packages as $result) {
            if (! $result->passes()) {
                $diagnostics[] = new TestingDiagnostic('packages.'.$result->name, $result->status === CompatibilityResult::Missing ? TestingDiagnostic::Error : TestingDiagnostic::Warning, $result->message);
            }
        }

        return new TestingReport($diagnostics, $packages);
    }

    /**
     * @return list<TestingDiagnostic>
     */
    private function dependencies(): array
    {
        $version = static fn (string $package): ?string => InstalledVersions::isInstalled($package) ? InstalledVersions::getPrettyVersion($package) : null;
        $phpunit = $version('phpunit/phpunit');

        return [
            new TestingDiagnostic('testing.phpunit', $phpunit === null ? TestingDiagnostic::Error : TestingDiagnostic::Ok, $phpunit === null ? 'PHPUnit is not installed.' : 'PHPUnit '.$phpunit.'.'),
            new TestingDiagnostic('testing.pest', TestingDiagnostic::Info, ($pest = $version('pestphp/pest')) === null ? 'Pest is not installed; the PHPUnit API is fully available.' : 'Pest '.$pest.'; call PestIntegration::register() for the expectations.'),
            new TestingDiagnostic('testing.livewire', class_exists('Livewire\Features\SupportTesting\Testable') ? TestingDiagnostic::Ok : TestingDiagnostic::Info, class_exists('Livewire\Features\SupportTesting\Testable') ? 'Livewire testing is available.' : 'Livewire is not installed; GcegoTest::livewire() is unavailable.'),
            new TestingDiagnostic('testing.testbench', TestingDiagnostic::Info, ($testbench = $version('orchestra/testbench')) === null ? 'Orchestra Testbench is not installed; PackageTestCase is unavailable.' : 'Orchestra Testbench '.$testbench.'.'),
        ];
    }

    private function database(): TestingDiagnostic
    {
        if (! $this->container->bound('db')) {
            return new TestingDiagnostic('database', TestingDiagnostic::Info, 'No database manager is bound.');
        }

        try {
            $connection = $this->container->make('db')->connection();
            $connection->getPdo();
            $name = (string) $connection->getName();
            $driver = (string) $connection->getDriverName();
        } catch (Throwable $exception) {
            return new TestingDiagnostic('database', TestingDiagnostic::Error, 'The default database connection failed: '.$exception->getMessage());
        }

        return new TestingDiagnostic('database', TestingDiagnostic::Ok, sprintf('Connection "%s" (%s) is reachable.', $name, $driver));
    }

    /**
     * @return list<TestingDiagnostic>
     */
    private function drivers(): array
    {
        $config = $this->container->bound('config') ? $this->container->make('config') : null;

        if ($config === null) {
            return [];
        }

        $cache = $config->get('cache.default');
        $queue = $config->get('queue.default');
        $mail = $config->get('mail.default');

        return [
            new TestingDiagnostic('isolation.cache', in_array($cache, ['array', 'null'], true) ? TestingDiagnostic::Ok : TestingDiagnostic::Warning, in_array($cache, ['array', 'null'], true) ? 'The cache is per process.' : sprintf('The cache store "%s" outlives the test; use "array" so cached values cannot leak between tests.', is_string($cache) ? $cache : '?')),
            new TestingDiagnostic('isolation.queue', in_array($queue, ['sync', 'null'], true) ? TestingDiagnostic::Ok : TestingDiagnostic::Warning, in_array($queue, ['sync', 'null'], true) ? 'Jobs run synchronously (or use Queue::fake()).' : sprintf('The queue "%s" sends jobs outside the test; use "sync" or Queue::fake().', is_string($queue) ? $queue : '?')),
            new TestingDiagnostic('isolation.mail', in_array($mail, ['array', 'log', null], true) ? TestingDiagnostic::Ok : TestingDiagnostic::Warning, in_array($mail, ['array', 'log', null], true) ? 'Mail is not sent.' : 'Mail may be delivered for real; use the "array" mailer or Mail::fake().'),
        ];
    }

    private function registryCache(): TestingDiagnostic
    {
        $enabled = $this->container->bound('config') && $this->container->make('config')->get('gcego-registry.cache.enabled') === true;

        return $enabled
            ? new TestingDiagnostic('isolation.registry', TestingDiagnostic::Warning, 'The registry cache is on: tests can read definitions cached by another run. Set gcego-registry.cache.enabled to false.')
            : new TestingDiagnostic('isolation.registry', TestingDiagnostic::Ok, 'The registry cache is off.');
    }

    private function tenancy(): TestingDiagnostic
    {
        $enabled = $this->container->bound('config') && $this->container->make('config')->get('gcego-security.tenancy.enabled') === true;

        return new TestingDiagnostic('tenancy', TestingDiagnostic::Info, $enabled ? 'Tenancy is enabled: use GcegoTest::tenant() to prove isolation.' : 'Tenancy is disabled.');
    }

    private function fixtures(): TestingDiagnostic
    {
        $directory = $this->config->fixturesDirectory ?? sys_get_temp_dir();

        return is_dir($directory) && is_writable($directory)
            ? new TestingDiagnostic('fixtures.directory', TestingDiagnostic::Ok, sprintf('Workspaces are created in %s.', $directory))
            : new TestingDiagnostic('fixtures.directory', TestingDiagnostic::Error, sprintf('The fixtures directory %s is not writable.', $directory));
    }

    private function parallel(): TestingDiagnostic
    {
        $token = getenv('TEST_TOKEN');

        return new TestingDiagnostic('parallel', TestingDiagnostic::Info, is_string($token) && $token !== '' ? sprintf('Parallel process %s: workspaces and cache prefixes include the token.', $token) : 'Not running in parallel.');
    }

    private function browser(): TestingDiagnostic
    {
        $reason = (new BrowserAccessibility($this->config->node, $this->config->nodeModules, $this->basePath()))->unavailableReason();

        return new TestingDiagnostic('accessibility.browser', TestingDiagnostic::Info, $reason === null ? 'Browser accessibility audits (Playwright and axe-core) are available.' : 'Browser accessibility audits are unavailable: '.$reason);
    }

    private function basePath(): ?string
    {
        return $this->container instanceof Application ? $this->container->basePath() : null;
    }
}
