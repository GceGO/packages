<?php

declare(strict_types=1);

namespace Gcego\Core\Diagnostics;

use Gcego\Core\Configuration\ConfigurationValidator;
use Gcego\Core\Exceptions\PackageDependencyException;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Lifecycle\FrameworkState;
use Gcego\Core\Lifecycle\LifecycleManager;
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
    public const string ComponentsPackageClass = 'Gcego\\Components\\Component';

    public function __construct(
        private EnvironmentChecker $environment,
        private ConfigurationValidator $configurationValidator,
        private Repository $config,
        private LifecycleManager $lifecycle,
        private PackageManager $packages,
        private ExtensionManager $extensions,
    ) {}

    public function run(): DiagnosticReport
    {
        $results = [];

        foreach ($this->checks() as $check => $run) {
            try {
                array_push($results, ...$run());
            } catch (Throwable $exception) {
                $results[] = DiagnosticResult::failed($check, sprintf('The check could not run (%s).', $exception::class));
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
            'packages.components' => fn (): array => [$this->components()],
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

        $problems = $this->packages->problems($disabled);
        $registered = array_map(static fn ($descriptor): string => $descriptor->id, $this->packages->descriptors());

        if ($problems === []) {
            return DiagnosticResult::passed('packages.dependencies', sprintf('%d GCEGO package(s) registered with satisfied dependencies.', count($registered)), ['packages' => $registered]);
        }

        return DiagnosticResult::failed(
            'packages.dependencies',
            implode(' ', array_map(static fn (PackageDependencyException $problem): string => $problem->getMessage(), $problems)),
            ['packages' => $registered],
        );
    }

    private function components(): DiagnosticResult
    {
        return class_exists(self::ComponentsPackageClass)
            ? DiagnosticResult::passed('packages.components', 'gcego/components is available.')
            : DiagnosticResult::warning('packages.components', 'gcego/components is not installed. Core does not need it, but GCEGO component packages do.');
    }

    private function failureClass(): string
    {
        $failure = $this->lifecycle->failure();

        while ($failure?->getPrevious() !== null) {
            $failure = $failure->getPrevious();
        }

        return $failure === null ? 'unknown error' : $failure::class;
    }
}
