<?php

declare(strict_types=1);

namespace Gcego\Core\Diagnostics;

use Gcego\Core\Configuration\ConfigurationValidator;
use Gcego\Core\Exceptions\ExtensionException;
use Gcego\Core\Exceptions\PackageDependencyException;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Lifecycle\FrameworkState;
use Gcego\Core\Lifecycle\LifecycleManager;
use Gcego\Core\Packages\PackageDiscovery;
use Gcego\Core\Packages\PackageManager;
use Illuminate\Contracts\Config\Repository;
use Throwable;

/**
 * Runs every framework diagnostic on demand and never throws.
 *
 * Intended for CLI tooling and health pages, not for every request.
 */
final readonly class FrameworkDiagnostics
{
    public function __construct(
        private EnvironmentChecker $environment,
        private ConfigurationValidator $configurationValidator,
        private Repository $config,
        private LifecycleManager $lifecycle,
        private PackageManager $packages,
        private ExtensionManager $extensions,
        private DiagnosticRegistry $registry = new DiagnosticRegistry,
        private ?PackageDiscovery $discovery = null,
        private ?CoreDiagnostics $infrastructure = null,
    ) {}

    public function run(): DiagnosticReport
    {
        $results = [];

        foreach ($this->checks() as $check => $run) {
            try {
                array_push($results, ...$run());
            } catch (Throwable $exception) {
                $results[] = DiagnosticResult::failed($check, sprintf('The check could not run (%s).', DiagnosticRedactor::exceptionClass($exception)));
            }
        }

        return new DiagnosticReport(...$results);
    }

    /**
     * @return array<string, callable(): list<DiagnosticResult>>
     */
    private function checks(): array
    {
        return [
            'environment' => $this->environment->check(...),
            'configuration' => fn (): array => [$this->configuration()],
            'framework.registration' => fn (): array => [$this->registration()],
            'framework.boot' => fn (): array => [$this->boot()],
            'packages.dependencies' => fn (): array => [$this->dependencies()],
            'extensions.dependencies' => fn (): array => [$this->extensionDependencies()],
            'framework.infrastructure' => fn (): array => $this->infrastructure?->run() ?? [],
            'diagnostics.custom' => $this->registry->run(...),
        ];
    }

    private function configuration(): DiagnosticResult
    {
        $config = $this->config->get('gcego', []);

        if (! is_array($config)) {
            return DiagnosticResult::failed('configuration', 'config("gcego") must be an array.');
        }

        $result = $this->configurationValidator->validate($config);

        if ($result['errors'] !== []) {
            return DiagnosticResult::failed('configuration', implode(' ', $result['errors']), ['errors' => count($result['errors'])]);
        }

        if ($result['warnings'] !== []) {
            return DiagnosticResult::warning('configuration', implode(' ', $result['warnings']), ['warnings' => count($result['warnings'])]);
        }

        return DiagnosticResult::passed('configuration', 'The GCEGO configuration is valid.');
    }

    private function registration(): DiagnosticResult
    {
        return $this->lifecycle->is(FrameworkState::Uninitialized)
            ? DiagnosticResult::failed('framework.registration', 'Gcego\Core\GcegoServiceProvider is not registered.')
            : DiagnosticResult::passed('framework.registration', 'The GCEGO service provider is registered.');
    }

    private function boot(): DiagnosticResult
    {
        $state = $this->lifecycle->state();
        $details = [
            'state' => $state->value,
            'packages' => $this->packages->booted(),
            'extensions' => $this->extensions->active(),
        ];

        return match ($state) {
            FrameworkState::Booted => DiagnosticResult::passed('framework.boot', 'GCEGO is booted.', $details),
            FrameworkState::Failed => DiagnosticResult::failed(
                'framework.boot',
                sprintf('GCEGO failed to boot (%s). Check the application log for details.', $this->failureClass()),
                $details,
            ),
            default => DiagnosticResult::warning('framework.boot', sprintf('GCEGO is not booted (state: %s).', $state->value), $details),
        };
    }

    private function dependencies(): DiagnosticResult
    {
        $config = $this->config->get('gcego', []);
        $disabled = is_array($config)
            ? $this->configurationValidator->validate($config)['values']['disabled_packages']
            : [];

        $packages = $this->discovery?->snapshot() ?? $this->packages;
        $problems = $packages->problems($disabled);
        $registered = array_map(static fn ($descriptor): string => $descriptor->id, $packages->descriptors());

        if ($problems === []) {
            return DiagnosticResult::passed('packages.dependencies', sprintf('%d GCEGO package(s) registered with satisfied dependencies.', count($registered)), ['packages' => $registered]);
        }

        return DiagnosticResult::failed(
            'packages.dependencies',
            implode(' ', array_map(static fn (PackageDependencyException $problem): string => $problem->getMessage(), $problems)),
            ['packages' => $registered],
        );
    }

    private function extensionDependencies(): DiagnosticResult
    {
        $raw = $this->config->get('gcego', []);
        $values = $this->configurationValidator->validate(is_array($raw) ? $raw : [])['values'];

        // Before boot, use the prospective package order without running hooks.
        $snapshot = $this->discovery?->snapshot() ?? $this->packages;

        try {
            $packages = $snapshot->resolve($values['disabled_packages']);
        } catch (PackageDependencyException) {
            return DiagnosticResult::warning('extensions.dependencies', 'Package dependency problems prevent extension planning; see packages.dependencies.');
        }

        try {
            $capabilities = [];
            foreach ($packages as $id) {
                array_push($capabilities, ...$snapshot->descriptor($id)->capabilities);
            }
            $plan = $this->extensions->plan($values['disabled_extensions'], $packages, $capabilities);
        } catch (ExtensionException $exception) {
            return DiagnosticResult::failed('extensions.dependencies', $exception->getMessage());
        }

        return DiagnosticResult::passed(
            'extensions.dependencies',
            sprintf('%d extension(s) eligible; %d skipped.', count($plan['order']), count($plan['skipped'])),
            ['extensions' => $plan['order'], 'skipped' => array_keys($plan['skipped'])],
        );
    }

    private function failureClass(): string
    {
        $failure = $this->lifecycle->failure();

        while ($failure?->getPrevious() !== null) {
            $failure = $failure->getPrevious();
        }

        return $failure === null ? 'unknown error' : DiagnosticRedactor::exceptionClass($failure);
    }
}
