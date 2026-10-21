<?php

declare(strict_types=1);

namespace Gcego\Core\Bootstrap;

use Gcego\Core\Configuration\GcegoConfig;
use Gcego\Core\Diagnostics\DiagnosticResult;
use Gcego\Core\Diagnostics\EnvironmentChecker;
use Gcego\Core\Diagnostics\InfrastructureIssues;
use Gcego\Core\Events\FrameworkBooted;
use Gcego\Core\Events\FrameworkBootFailed;
use Gcego\Core\Events\FrameworkBooting;
use Gcego\Core\Exceptions\BootstrapException;
use Gcego\Core\Exceptions\ExtensionException;
use Gcego\Core\Exceptions\InvalidLifecycleException;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Lifecycle\LifecycleManager;
use Gcego\Core\Packages\PackageDiscovery;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Support\ExclusiveCapabilities;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

/**
 * Boots GCEGO once, after Laravel has booted every service provider.
 *
 *  1. Lifecycle: Registered|Failed -> Booting.
 *  2. Read and validate configuration; stop early (back to Registered) when disabled.
 *  3. Optionally run the environment checks.
 *  4. Discover loaded service providers implementing PackageInterface and lock package registration.
 *  5. Validate dependencies and boot packages in dependency order.
 *  6. Refuse exclusive capabilities with more than one provider, then
 *     activate extensions in dependency order.
 *  7. Lock extension registration; lifecycle -> Booted.
 *
 * Any failure moves the lifecycle to Failed and throws BootstrapException.
 * boot() may then be called again; work that already succeeded is not repeated.
 */
final class FrameworkBootstrapper
{
    private ?BootstrapResult $result = null;

    private bool $initializing = false;

    public function __construct(
        private readonly Application $app,
        private readonly LifecycleManager $lifecycle,
        private readonly PackageManager $packages,
        private readonly ExtensionManager $extensions,
        private readonly StartupTelemetry $telemetry = new StartupTelemetry,
        private readonly InfrastructureIssues $issues = new InfrastructureIssues,
    ) {}

    /**
     * @throws BootstrapException
     */
    public function boot(): BootstrapResult
    {
        if ($this->lifecycle->isBooted() && $this->result !== null) {
            return $this->result;
        }

        if ($this->initializing) {
            $this->issues->record('framework.recursive_boot');
            throw new InvalidLifecycleException('The framework is already booting; recursive boot is not allowed.');
        }

        $started = hrtime(true);
        $this->lifecycle->beginBoot();
        $this->initializing = true;
        $this->telemetry->beginAttempt();

        try {
            $config = $this->app->make(GcegoConfig::class);

            if (! $config->enabled()) {
                $this->lifecycle->cancelBoot();

                return $this->result = BootstrapResult::disabled();
            }

            $this->telemetry->configure($config->profiling());
            $this->telemetry->publish(new FrameworkBooting);

            if ($config->environmentChecks()) {
                $this->checkEnvironment();
            }

            $this->telemetry->phase('registration', fn () => (new PackageDiscovery($this->app, $this->packages, $this->issues))->discover());
            $this->packages->lock();

            $order = $this->telemetry->phase('dependencies', fn () => $this->packages->resolve($config->disabledPackages()));
            $context = new BootstrapContext($this->app, $config, $order, $this->capabilities($order));

            $this->telemetry->phase('packages', fn () => $this->packages->boot($order, $context));
            $this->telemetry->phase('extensions', function () use ($context, $config): void {
                $this->assertExclusiveCapabilities($context, $config->disabledExtensions());
                $this->extensions->activate($context, $config->disabledExtensions());
            });

            $this->extensions->lock();
            $this->lifecycle->completeBoot();
        } catch (Throwable $exception) {
            $failure = $exception instanceof BootstrapException ? $exception : BootstrapException::failed($exception);
            $this->lifecycle->fail($failure);
            $this->telemetry->publish(new FrameworkBootFailed($failure, round((hrtime(true) - $started) / 1e6, 3)));

            throw $failure;
        } finally {
            $this->telemetry->finishAttempt(round((hrtime(true) - $started) / 1e6, 3));
            $this->initializing = false;
        }

        $this->result = new BootstrapResult(
            BootStatus::Booted,
            $this->packages->booted(),
            $this->extensions->active(),
            $this->extensions->skipped(),
            round((hrtime(true) - $started) / 1e6, 3),
            $this->telemetry->timings(),
        );

        $this->telemetry->publish(new FrameworkBooted($this->result));

        return $this->result;
    }

    public function result(): ?BootstrapResult
    {
        return $this->result;
    }

    private function checkEnvironment(): void
    {
        $failures = $this->app->make(EnvironmentChecker::class)->failures();

        if ($failures !== []) {
            throw BootstrapException::environment(array_map(static fn (DiagnosticResult $result): string => $result->message, $failures));
        }
    }

    /**
     * Package-only conflicts already failed dependency resolution, so any
     * conflict found here involves an extension that has not activated yet.
     *
     * @param  list<string>  $disabledExtensions
     *
     * @throws ExtensionException
     */
    private function assertExclusiveCapabilities(BootstrapContext $context, array $disabledExtensions): void
    {
        $plan = $this->extensions->plan($disabledExtensions, $context->packages, $context->capabilities);
        $providers = [
            ...array_map($this->packages->descriptor(...), $context->packages),
            ...array_map($this->extensions->descriptor(...), $plan['order']),
        ];

        foreach (ExclusiveCapabilities::conflicts($providers) as $capability => $ids) {
            throw ExtensionException::exclusiveCapability($capability, $ids);
        }
    }

    /**
     * @param  list<string>  $packages
     * @return list<string>
     */
    private function capabilities(array $packages): array
    {
        $capabilities = [];

        foreach ($packages as $id) {
            array_push($capabilities, ...$this->packages->descriptor($id)->capabilities);
        }

        return array_values(array_unique($capabilities));
    }
}
