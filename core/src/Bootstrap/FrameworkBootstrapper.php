<?php

declare(strict_types=1);

namespace Gcego\Core\Bootstrap;

use Gcego\Core\Configuration\GcegoConfig;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Diagnostics\DiagnosticResult;
use Gcego\Core\Diagnostics\EnvironmentChecker;
use Gcego\Core\Exceptions\BootstrapException;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Lifecycle\LifecycleManager;
use Gcego\Core\Packages\PackageManager;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

/**
 * Boots GCEGO once, after Laravel has booted every service provider.
 *
 *  1. Lifecycle: Registered|Failed -> Booting.
 *  2. Read and validate configuration; stop early (back to Registered) when disabled.
 *  3. Optionally run the environment checks.
 *  4. Discover loaded service providers implementing PackageInterface.
 *  5. Validate dependencies and boot packages in dependency order.
 *  6. Activate extensions in dependency order.
 *  7. Lock registration; lifecycle -> Booted.
 *
 * Any failure moves the lifecycle to Failed and throws BootstrapException.
 * boot() may then be called again; work that already succeeded is not repeated.
 */
final class FrameworkBootstrapper
{
    private ?BootstrapResult $result = null;

    public function __construct(
        private readonly Application $app,
        private readonly LifecycleManager $lifecycle,
        private readonly PackageManager $packages,
        private readonly ExtensionManager $extensions,
    ) {}

    /**
     * @throws BootstrapException
     */
    public function boot(): BootstrapResult
    {
        if ($this->lifecycle->isBooted() && $this->result !== null) {
            return $this->result;
        }

        $started = hrtime(true);
        $this->lifecycle->beginBoot();

        try {
            $config = $this->app->make(GcegoConfig::class);

            if (! $config->enabled()) {
                $this->lifecycle->cancelBoot();

                return BootstrapResult::disabled();
            }

            if ($config->environmentChecks()) {
                $this->checkEnvironment();
            }

            foreach ($this->app->getProviders(PackageInterface::class) as $provider) {
                if ($provider instanceof PackageInterface) {
                    $this->packages->register($provider);
                }
            }

            $order = $this->packages->resolve($config->disabledPackages());
            $context = new BootstrapContext($this->app, $config, $order, $this->capabilities($order));

            $this->packages->boot($order, $context);
            $this->extensions->activate($context, $config->disabledExtensions());

            $this->packages->lock();
            $this->extensions->lock();
            $this->lifecycle->completeBoot();
        } catch (Throwable $exception) {
            $failure = $exception instanceof BootstrapException ? $exception : BootstrapException::failed($exception);
            $this->lifecycle->fail($failure);

            throw $failure;
        }

        return $this->result = new BootstrapResult(
            BootStatus::Booted,
            $this->packages->booted(),
            $this->extensions->active(),
            $this->extensions->skipped(),
            round((hrtime(true) - $started) / 1e6, 3),
        );
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
