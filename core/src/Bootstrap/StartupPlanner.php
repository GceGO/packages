<?php

declare(strict_types=1);

namespace Gcego\Core\Bootstrap;

use Gcego\Core\Configuration\GcegoConfig;
use Gcego\Core\Diagnostics\DiagnosticRedactor;
use Gcego\Core\Exceptions\ConfigurationException;
use Gcego\Core\Exceptions\ExtensionException;
use Gcego\Core\Exceptions\PackageRegistrationException;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Packages\PackageDiscovery;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

final readonly class StartupPlanner
{
    public function __construct(private Application $app, private PackageDiscovery $discovery, private ExtensionManager $extensions) {}

    public function plan(): BootstrapPlan
    {
        try {
            $config = $this->app->make(GcegoConfig::class);
            $packages = $this->discovery->snapshot();
            $problems = array_map(static fn ($problem): string => $problem->getMessage(), $packages->problems($config->disabledPackages()));
            $order = $problems === [] ? $packages->resolve($config->disabledPackages()) : [];
            $extensionPlan = ['order' => [], 'skipped' => []];

            if ($problems === []) {
                try {
                    $packageCapabilities = [];
                    foreach ($order as $id) {
                        array_push($packageCapabilities, ...$packages->descriptor($id)->capabilities);
                    }
                    $extensionPlan = $this->extensions->plan($config->disabledExtensions(), $order, $packageCapabilities);
                } catch (ExtensionException $exception) {
                    $problems[] = $exception->getMessage();
                }
            }

            $capabilities = [];

            foreach ($order as $id) {
                array_push($capabilities, ...$packages->descriptor($id)->capabilities);
            }

            foreach ($extensionPlan['order'] as $id) {
                array_push($capabilities, ...$this->extensions->descriptor($id)->capabilities);
            }

            $capabilities = array_values(array_unique($capabilities));
            sort($capabilities);

            return new BootstrapPlan($config->enabled(), $order, $extensionPlan['order'], $extensionPlan['skipped'], $config->disabledPackages(), $capabilities, $problems);
        } catch (ConfigurationException|PackageRegistrationException $exception) {
            return new BootstrapPlan(false, problems: [$exception->getMessage()]);
        } catch (Throwable $exception) {
            return new BootstrapPlan(false, problems: [sprintf('Startup planning could not run (%s).', DiagnosticRedactor::exceptionClass($exception))]);
        }
    }
}
