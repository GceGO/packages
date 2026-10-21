<?php

declare(strict_types=1);

namespace Gcego\Core\Diagnostics;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Gcego\Core\Bootstrap\FrameworkBootstrapper;
use Gcego\Core\Bootstrap\StartupPlanner;
use Gcego\Core\Bootstrap\StartupTelemetry;
use Gcego\Core\Configuration\ConfigurationValidator;
use Gcego\Core\Configuration\GcegoConfig;
use Gcego\Core\Console\DiagnoseCommand;
use Gcego\Core\Console\PlanCommand;
use Gcego\Core\Contracts\FrameworkInterface;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Exceptions\ExtensionException;
use Gcego\Core\Exceptions\PackageDependencyException;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Gcego;
use Gcego\Core\Lifecycle\FrameworkState;
use Gcego\Core\Lifecycle\LifecycleManager;
use Gcego\Core\Packages\PackageDiscovery;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Support\ExclusiveCapabilities;
use Gcego\Core\Support\FrameworkVersion;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Throwable;

/** On-demand inspection of Core infrastructure. Never calls package boot hooks. */
final readonly class CoreDiagnostics
{
    public function __construct(
        private Application $app,
        private LifecycleManager $lifecycle,
        private PackageManager $packages,
        private ExtensionManager $extensions,
        private StartupTelemetry $telemetry,
        private InfrastructureIssues $issues,
        private IntegrationState $integration,
        private PackageDiscovery $discovery,
        private ConfigurationValidator $validator,
    ) {}

    /** @return list<DiagnosticResult> */
    public function run(): array
    {
        $results = [];
        $snapshot = $this->packages;
        try {
            $snapshot = $this->discovery->snapshot();
            $results[] = DiagnosticResult::passed('packages.discovery', 'Loaded GCEGO providers have valid registration metadata.');
        } catch (Throwable $exception) {
            $results[] = DiagnosticResult::failed('packages.discovery', 'Provider discovery could not complete.', ['exception' => DiagnosticRedactor::exceptionClass($exception)]);
        }

        foreach (
            [
                'framework.identity' => fn () => $this->identity(),
                'lifecycle.history' => fn () => $this->lifecycle(),
                'packages.inventory' => fn () => $this->packageInventory($snapshot),
                'packages.integrity' => fn () => $this->packageIntegrity($snapshot),
                'extensions.inventory' => fn () => $this->extensionInventory(),
                'extensions.integrity' => fn () => $this->extensionIntegrity($snapshot),
                'configuration.references' => fn () => $this->configuration($snapshot),
                'laravel.integration' => fn () => $this->integration(),
                'startup.failures' => fn () => $this->failures($snapshot),
                'capabilities.integrity' => fn () => $this->capabilities(),
                'performance.bootstrap' => fn () => $this->performance(),
                'security.operations' => fn () => $this->operations(),
                'registration.incidents' => fn () => $this->incidents(),
            ] as $code => $check
        ) {
            try {
                array_push($results, ...$check());
            } catch (Throwable $exception) {
                $results[] = DiagnosticResult::failed($code, 'The infrastructure check could not run.', ['exception' => DiagnosticRedactor::exceptionClass($exception)]);
            }
        }

        return $results;
    }

    /** @return array<mixed> */
    private function rawConfig(): array
    {
        $raw = $this->app->make('config')->get('gcego', []);

        return is_array($raw) ? $raw : [];
    }

    /** @return list<DiagnosticResult> */
    private function identity(): array
    {
        $version = FrameworkVersion::detect();
        $runtime = defined('Composer\\Composer::RUNTIME_API_VERSION') ? constant('Composer\\Composer::RUNTIME_API_VERSION') : null;
        $reference = $version->reference;
        $env = $this->app->environment();

        return [
            DiagnosticResult::passed('framework.identity', 'GCEGO Studio Core.', ['name' => 'GCEGO Studio', 'version' => $version->version]),
            DiagnosticResult::passed('framework.build', $reference === null ? 'No Composer build reference is available.' : 'Composer build metadata is available.', ['reference' => $reference !== null && preg_match('/^[a-f0-9]{7,64}$/i', $reference) ? $reference : null]),
            DiagnosticResult::passed('composer.runtime', is_string($runtime) ? 'Composer runtime API metadata is available.' : 'Composer runtime API version is not exposed by this installation.', ['version' => is_string($runtime) ? $runtime : null, 'required' => '^2.2']),
            DiagnosticResult::passed('runtime.platform', 'Runtime platform and SAPI.', ['os' => PHP_OS_FAMILY, 'sapi' => PHP_SAPI]),
            DiagnosticResult::passed('application.environment', 'Laravel application environment.', ['environment' => is_string($env) && in_array($env, ['production', 'local', 'testing', 'staging', 'development'], true) ? $env : 'custom']),
        ];
    }

    /** @return list<DiagnosticResult> */
    private function lifecycle(): array
    {
        $history = $this->lifecycle->history();
        $rejected = array_values(array_filter($history, static fn ($entry): bool => ! $entry['accepted']));
        $rows = [DiagnosticResult::passed('lifecycle.history', 'Recorded lifecycle transitions (bounded to the latest 100).', ['state' => $this->lifecycle->state()->value, 'transitions' => array_map(static fn ($entry): string => $entry['timestamp'].' '.$entry['from'].' -> '.$entry['to'].' '.($entry['accepted'] ? 'accepted' : 'rejected'), $history)])];
        $rows[] = $rejected === []
            ? DiagnosticResult::passed('lifecycle.transitions', 'No invalid lifecycle transitions have been recorded.')
            : DiagnosticResult::warning('lifecycle.transitions', 'Invalid lifecycle transitions were rejected.', ['count' => count($rejected)]);

        return $rows;
    }

    /** @return list<DiagnosticResult> */
    private function packageInventory(PackageManager $snapshot): array
    {
        $disabled = $this->validator->validate($this->rawConfig())['values']['disabled_packages'];
        $steps = $this->telemetry->steps();
        $failed = [];
        $rows = [];
        foreach ($snapshot->descriptors() as $descriptor) {
            $step = $steps['package:'.$descriptor->id] ?? null;
            $status = $step['status'] ?? ($this->packages->isBooted($descriptor->id) ? 'booted' : (in_array($descriptor->id, $disabled, true) ? 'disabled' : 'pending'));
            if ($status === 'failed') {
                $failed[] = $descriptor->id;
            }
            $rows[] = new DiagnosticResult('packages.status.'.$descriptor->id, $status === 'failed' ? DiagnosticStatus::Failed : DiagnosticStatus::Passed, 'Package initialization metadata.', [
                'status' => $status,
                'version' => $descriptor->version,
                'requires' => $descriptor->requires,
                'optional' => $descriptor->optional,
                'capabilities' => $descriptor->capabilities,
                'exception' => $step['exception'] ?? null,
                'attempts' => $step['attempts'] ?? 0,
            ], package: $descriptor->id);
        }
        array_unshift($rows, DiagnosticResult::passed('packages.inventory', 'Recognized GCEGO package inventory.', ['total' => count($snapshot->descriptors()), 'registered' => count($this->packages->descriptors()), 'active' => $this->packages->booted(), 'disabled' => $disabled, 'failed' => $failed]));
        try {
            $rows[] = DiagnosticResult::passed('packages.order', 'Resolved and actual package initialization order.', ['resolved' => $snapshot->resolve($disabled), 'actual' => $this->packages->booted()]);
        } catch (Throwable) {
            $rows[] = DiagnosticResult::failed('packages.order', 'A complete package order cannot be resolved; inspect dependency diagnostics.', ['actual' => $this->packages->booted()]);
        }

        return $rows;
    }

    /** @return list<DiagnosticResult> */
    private function packageIntegrity(PackageManager $snapshot): array
    {
        $disabled = $this->validator->validate($this->rawConfig())['values']['disabled_packages'];
        $dependencyProblems = [];
        foreach ($snapshot->problems($disabled) as $problem) {
            $dependencyProblems[$problem->diagnosticCode][] = $problem->getMessage();
        }
        $positions = array_flip($this->packages->booted());
        $violations = [];
        $rows = [];
        foreach ($dependencyProblems as $code => $messages) {
            $rows[] = DiagnosticResult::failed($code, implode(' ', $messages));
        }
        foreach ($snapshot->descriptors() as $descriptor) {
            if (isset($positions[$descriptor->id])) {
                foreach ($descriptor->requires as $dependency) {
                    if (! isset($positions[$dependency]) || $positions[$dependency] >= $positions[$descriptor->id]) {
                        $violations[] = $descriptor->id.' -> '.$dependency;
                    }
                }
            }
            $unknown = [];
            foreach ($descriptor->versionConstraints as $dependency => $constraint) {
                if ($snapshot->has($dependency) && $snapshot->descriptor($dependency)->version === null) {
                    $unknown[] = $dependency;
                }
            }
            if ($unknown !== []) {
                $rows[] = DiagnosticResult::warning('packages.versions.'.$descriptor->id, 'Some declared version constraints cannot be verified.', ['dependencies' => $unknown], package: $descriptor->id);
            }
            if ($descriptor->version !== null && InstalledVersions::isInstalled($descriptor->id)) {
                $installed = InstalledVersions::getVersion($descriptor->id);
                if ($installed !== null) {
                    try {
                        $declared = (new VersionParser)->normalize($descriptor->version);
                        $rows[] = $declared === $installed
                            ? DiagnosticResult::passed('packages.composer.'.$descriptor->id, 'Descriptor and Composer versions agree.', package: $descriptor->id)
                            : DiagnosticResult::failed('packages.composer.'.$descriptor->id, 'Descriptor version disagrees with installed Composer metadata.', package: $descriptor->id);
                    } catch (\UnexpectedValueException) {
                        $rows[] = DiagnosticResult::warning('packages.composer.'.$descriptor->id, 'Descriptor version cannot be compared with Composer metadata.', package: $descriptor->id);
                    }
                }
            }
        }
        $rows[] = $violations === []
            ? DiagnosticResult::passed('packages.ordering', 'No required-dependency initialization ordering violations were found.')
            : DiagnosticResult::failed('packages.ordering', 'Some active packages initialized before their requirements.', ['violations' => $violations]);

        return $rows;
    }

    /** @return list<DiagnosticResult> */
    private function extensionInventory(): array
    {
        $disabled = $this->validator->validate($this->rawConfig())['values']['disabled_extensions'];
        $steps = $this->telemetry->steps();
        $rows = [];
        $ids = [];
        $failed = [];
        foreach ($this->extensions->descriptors() as $descriptor) {
            $ids[] = $descriptor->id;
            $step = $steps['extension:'.$descriptor->id] ?? null;
            $status = $step['status'] ?? ($this->extensions->isActive($descriptor->id) ? 'booted' : (in_array($descriptor->id, $disabled, true) ? 'disabled' : (isset($this->extensions->skipped()[$descriptor->id]) ? 'skipped' : 'pending')));
            if ($status === 'failed') {
                $failed[] = $descriptor->id;
            }
            $rows[] = new DiagnosticResult('extensions.status.'.$descriptor->id, $status === 'failed' ? DiagnosticStatus::Failed : DiagnosticStatus::Passed, 'Extension initialization metadata.', ['status' => $status, 'requires' => $descriptor->requires, 'optional' => $descriptor->optional, 'when_packages' => $descriptor->whenPackages, 'capabilities' => $descriptor->capabilities, 'requires_capabilities' => $descriptor->requiresCapabilities, 'exception' => $step['exception'] ?? null, 'attempts' => $step['attempts'] ?? 0, 'skip_reason' => $this->extensions->skipped()[$descriptor->id] ?? null], extension: $descriptor->id);
        }
        array_unshift($rows, DiagnosticResult::passed('extensions.inventory', 'Registered extension inventory and actual activation order.', ['registered' => $ids, 'active' => $this->extensions->active(), 'disabled' => $disabled, 'failed' => $failed]));

        return $rows;
    }

    /** @return list<DiagnosticResult> */
    private function extensionIntegrity(PackageManager $snapshot): array
    {
        $values = $this->validator->validate($this->rawConfig())['values'];
        $capabilities = [];
        try {
            $order = $snapshot->resolve($values['disabled_packages']);
        } catch (PackageDependencyException) {
            return [DiagnosticResult::warning('extensions.planning', 'Package dependency problems prevent extension planning.')];
        }
        foreach ($order as $id) {
            array_push($capabilities, ...$snapshot->descriptor($id)->capabilities);
        }
        try {
            $plan = $this->extensions->plan($values['disabled_extensions'], $order, $capabilities);
            $providers = [
                ...array_map($snapshot->descriptor(...), $order),
                ...array_map($this->extensions->descriptor(...), $plan['order']),
            ];

            foreach (ExclusiveCapabilities::conflicts($providers) as $capability => $ids) {
                throw ExtensionException::exclusiveCapability($capability, $ids);
            }

            return [DiagnosticResult::passed('extensions.order', 'Resolved and actual extension activation order.', ['resolved' => $plan['order'], 'actual' => $this->extensions->active(), 'skipped' => array_keys($plan['skipped'])])];
        } catch (ExtensionException $exception) {
            return [DiagnosticResult::failed($exception->diagnosticCode, $exception->getMessage())];
        }
    }

    /** @return list<DiagnosticResult> */
    private function configuration(PackageManager $snapshot): array
    {
        $values = $this->validator->validate($this->rawConfig())['values'];
        $knownPackages = array_map(static fn ($descriptor): string => $descriptor->id, $snapshot->descriptors());
        $knownExtensions = array_map(static fn ($descriptor): string => $descriptor->id, $this->extensions->descriptors());
        $unknownPackages = array_values(array_diff($values['disabled_packages'], $knownPackages));
        $unknownExtensions = array_values(array_diff($values['disabled_extensions'], $knownExtensions));
        $rows = [
            DiagnosticResult::passed('configuration.required', 'All required Core options have defaults; no additional required settings exist.'),
            DiagnosticResult::passed('configuration.deprecated', 'This Core version declares no deprecated configuration keys.'),
            DiagnosticResult::passed('configuration.dynamic_classes', 'Core configuration accepts no dynamic class references; unsupported options are not autoloaded.'),
            DiagnosticResult::passed('laravel.configuration_cache', 'Laravel configuration cache status.', ['cached' => $this->app->configurationIsCached()]),
        ];
        $rows[] = $unknownPackages === [] ? DiagnosticResult::passed('configuration.packages', 'Disabled package references are recognized.') : DiagnosticResult::warning('configuration.packages', 'Disabled package references include unrecognized IDs.', ['unknown' => $unknownPackages]);
        $rows[] = $unknownExtensions === [] ? DiagnosticResult::passed('configuration.extensions', 'Disabled extension references are recognized.') : DiagnosticResult::warning('configuration.extensions', 'Disabled extension references include unrecognized IDs.', ['unknown' => $unknownExtensions]);

        return $rows;
    }

    /** @return list<DiagnosticResult> */
    private function integration(): array
    {
        $rows = [$this->integration->registered && $this->integration->providerBooted
            ? DiagnosticResult::passed('laravel.provider', 'Core provider registration and Laravel provider boot completed.')
            : DiagnosticResult::failed('laravel.provider', 'Core provider integration is incomplete.', ['registered' => $this->integration->registered, 'booted' => $this->integration->providerBooted])];
        $missing = [];
        $broken = [];
        $resolutionFailures = [];
        foreach ([Gcego::class, FrameworkInterface::class, GcegoConfig::class, LifecycleManager::class, PackageManager::class, ExtensionManager::class, FrameworkBootstrapper::class, StartupTelemetry::class, StartupPlanner::class, PackageDiscovery::class, DiagnosticRegistry::class, InfrastructureIssues::class, IntegrationState::class, FrameworkVersion::class] as $service) {
            if (! $this->app->bound($service)) {
                $missing[] = $service;

                continue;
            }
            try {
                $resolved = $this->app->make($service);
                if (! $resolved instanceof $service) {
                    $broken[] = $service;
                }
            } catch (Throwable $exception) {
                $broken[] = $service;
                $resolutionFailures[] = $service.': '.DiagnosticRedactor::exceptionClass($exception);
            }
        }
        $rows[] = $missing === [] && $broken === []
            ? DiagnosticResult::passed('laravel.bindings', 'Required Core bindings resolve to the expected contracts.')
            : DiagnosticResult::failed('laravel.bindings', 'Core container bindings are missing or fail resolution.', ['missing' => $missing, 'broken' => $broken, 'resolution_exceptions' => $resolutionFailures]);
        $rows[] = $this->app->isBooted()
            ? DiagnosticResult::passed('laravel.lifecycle', 'Laravel provider boot has completed.')
            : DiagnosticResult::warning('laravel.lifecycle', 'Laravel provider boot has not completed.');
        $deferred = [];
        $unverified = 0;
        foreach (array_unique(array_filter($this->app->getDeferredServices(), is_string(...))) as $provider) {
            if (! class_exists($provider, false)) {
                $unverified++;

                continue;
            }
            if (is_subclass_of($provider, PackageInterface::class)) {
                $deferred[] = $provider;
            }
        }
        foreach ($this->app->getProviders(PackageInterface::class) as $provider) {
            if ($provider instanceof DeferrableProvider) {
                $deferred[] = DiagnosticRedactor::className($provider::class);
            }
        }
        $rows[] = $deferred === []
            ? DiagnosticResult::passed('laravel.deferred_providers', 'No loaded GCEGO provider violates the non-deferred contract.', ['unloaded_providers_not_inspected' => $unverified])
            : DiagnosticResult::failed('laravel.deferred_providers', 'GCEGO package providers must not be deferred.', ['providers' => array_values(array_unique($deferred))]);
        $commandFailures = [];
        foreach ([DiagnoseCommand::class, PlanCommand::class] as $command) {
            try {
                $this->app->make($command);
            } catch (Throwable) {
                $commandFailures[] = $command;
            }
        }
        $rows[] = (! $this->app->runningInConsole() || $this->integration->commandsRegistered) && $commandFailures === []
            ? DiagnosticResult::passed('laravel.commands', 'Core command classes resolve and console registration is scheduled when applicable.', ['console' => $this->app->runningInConsole()])
            : DiagnosticResult::failed('laravel.commands', 'Core command registration or resolution is incomplete.', ['broken' => $commandFailures]);

        return $rows;
    }

    /** @return list<DiagnosticResult> */
    private function failures(PackageManager $snapshot): array
    {
        $rootPackage = null;
        $rootExtension = null;
        foreach ($this->telemetry->steps() as $key => $step) {
            if ($step['status'] !== 'failed') {
                continue;
            }
            [$kind, $id] = explode(':', $key, 2);
            if ($kind === 'package' && $rootPackage === null) {
                $rootPackage = $id;
            }
            if ($kind === 'extension' && $rootExtension === null) {
                $rootExtension = $id;
            }
        }
        $blocked = $rootPackage !== null && $snapshot->has($rootPackage)
            ? array_values(array_filter($snapshot->dependants($rootPackage, true), fn (string $id): bool => ! $this->packages->isBooted($id))) : [];
        $blockedExtensions = [];
        do {
            $changed = false;
            foreach ($this->extensions->descriptors() as $descriptor) {
                if ($descriptor->id === $rootExtension || $this->extensions->isActive($descriptor->id) || in_array($descriptor->id, $blockedExtensions, true)) {
                    continue;
                }
                if (array_intersect($descriptor->requires, [...$blockedExtensions, ...($rootExtension === null ? [] : [$rootExtension])]) !== [] || array_intersect($descriptor->whenPackages, [...$blocked, ...($rootPackage === null ? [] : [$rootPackage])]) !== []) {
                    $blockedExtensions[] = $descriptor->id;
                    $changed = true;
                }
            }
        } while ($changed);
        $failure = $this->lifecycle->failure();
        while ($failure?->getPrevious() !== null) {
            $failure = $failure->getPrevious();
        }
        $rows = [$this->lifecycle->is(FrameworkState::Failed)
            ? DiagnosticResult::failed('startup.failure', 'Core initialization failed; inspect the application logs through an authorized channel.', ['exception' => $failure === null ? null : DiagnosticRedactor::exceptionClass($failure), 'root_package' => $rootPackage, 'root_extension' => $rootExtension, 'blocked_packages' => $blocked, 'blocked_extensions' => $blockedExtensions], package: $rootPackage, extension: $rootExtension)
            : DiagnosticResult::passed('startup.failure', 'No current framework initialization failure.')];
        $partial = ! $this->lifecycle->isBooted() && ($this->packages->booted() !== [] || $this->extensions->active() !== []);
        $inconsistent = $this->lifecycle->isBooted() && (! $this->packages->isLocked() || ! $this->extensions->isLocked());
        $rows[] = $inconsistent ? DiagnosticResult::failed('startup.consistency', 'Booted Core has an open registration set.') : ($partial ? DiagnosticResult::warning('startup.consistency', 'Core is partially initialized; successful steps remain active for retry.') : DiagnosticResult::passed('startup.consistency', 'Lifecycle and initialization state are consistent.'));

        return $rows;
    }

    /** @return list<DiagnosticResult> */
    private function capabilities(): array
    {
        $providers = [];
        $exclusive = [];
        foreach ($this->packages->booted() as $id) {
            array_push($exclusive, ...$this->packages->descriptor($id)->exclusiveCapabilities);
            foreach ($this->packages->descriptor($id)->capabilities as $capability) {
                $providers[$capability]['packages'][] = $id;
            }
        }
        foreach ($this->extensions->active() as $id) {
            array_push($exclusive, ...$this->extensions->descriptor($id)->exclusiveCapabilities);
            foreach ($this->extensions->descriptor($id)->capabilities as $capability) {
                $providers[$capability]['extensions'][] = $id;
            }
        }
        ksort($providers);
        $rows = [DiagnosticResult::passed('capabilities.available', 'Capabilities provided by active components.', ['capabilities' => array_keys($providers)])];
        foreach ($providers as $capability => $groups) {
            $packages = $groups['packages'] ?? [];
            $extensions = $groups['extensions'] ?? [];
            $conflict = in_array($capability, $exclusive, true) && count($packages) + count($extensions) > 1;
            $rows[] = new DiagnosticResult('capabilities.providers.'.$capability, $conflict ? DiagnosticStatus::Failed : DiagnosticStatus::Passed, $conflict ? 'An exclusive capability has multiple active providers.' : 'Active capability providers; multiple ordinary providers are informational.', ['packages' => $packages, 'extensions' => $extensions, 'count' => count($packages) + count($extensions), 'exclusive' => in_array($capability, $exclusive, true)], resolution: $conflict ? 'Disable conflicting providers so exactly one provider of the exclusive capability is active.' : null);
        }
        foreach ($this->extensions->descriptors() as $descriptor) {
            $missing = array_values(array_diff($descriptor->requiresCapabilities, array_keys($providers)));
            if ($missing !== []) {
                $rows[] = $this->extensions->isActive($descriptor->id)
                    ? DiagnosticResult::failed('capabilities.required.'.$descriptor->id, 'An active extension is missing required capabilities.', ['missing' => $missing], extension: $descriptor->id)
                    : DiagnosticResult::warning('capabilities.required.'.$descriptor->id, 'Required capabilities are not currently active for this extension.', ['missing' => $missing], extension: $descriptor->id);
            }
        }

        return $rows;
    }

    /** @return list<DiagnosticResult> */
    private function performance(): array
    {
        $values = $this->validator->validate($this->rawConfig())['values'];
        $duration = $this->telemetry->durationMs();
        $phases = $this->telemetry->phases();
        $rows = [new DiagnosticResult('performance.bootstrap', $duration !== null && $duration > $values['slow_boot_ms'] ? DiagnosticStatus::Warning : DiagnosticStatus::Passed, 'Latest Core bootstrap and phase timing in milliseconds.', ['duration_ms' => $duration, 'registration_ms' => $phases['registration'] ?? null, 'dependencies_ms' => $phases['dependencies'] ?? null, 'packages_ms' => $phases['packages'] ?? null, 'extensions_ms' => $phases['extensions'] ?? null, 'profiling' => $this->telemetry->profiling()])];
        $attempts = [];
        foreach ($this->telemetry->timings() as $timing) {
            $key = $timing->kind.':'.$timing->id;
            $attempts[$key] = ($attempts[$key] ?? 0) + 1;
            $threshold = $timing->kind === 'package' ? $values['slow_package_ms'] : $values['slow_extension_ms'];
            $rows[] = new DiagnosticResult('performance.'.$timing->kind.'.'.$timing->id.'.attempt.'.$attempts[$key], $timing->durationMs > $threshold ? DiagnosticStatus::Warning : DiagnosticStatus::Passed, 'Recorded initialization attempt timing.', ['duration_ms' => $timing->durationMs, 'succeeded' => $timing->succeeded, 'threshold_ms' => $threshold], package: $timing->kind === 'package' ? $timing->id : null, extension: $timing->kind === 'extension' ? $timing->id : null);
        }

        return $rows;
    }

    /** @return list<DiagnosticResult> */
    private function operations(): array
    {
        $production = $this->app->environment('production');
        $debug = (bool) $this->app->make('config')->get('app.debug');
        $values = $this->validator->validate($this->rawConfig())['values'];
        $unsafe = $production && (! $values['environment_checks'] || ($values['enabled'] && ! $values['auto_boot']));

        return [
            $production && $debug ? DiagnosticResult::warning('security.production_debug', 'Laravel debug mode is enabled in production.') : DiagnosticResult::passed('security.production_debug', 'Production debug mode is not enabled.'),
            $unsafe ? DiagnosticResult::warning('configuration.environment', 'Production disables automatic startup or environment validation; confirm this is intentional.') : DiagnosticResult::passed('configuration.environment', 'No unsafe Core production configuration combinations detected.'),
            DiagnosticResult::passed('security.diagnostic_access', 'Core registers no HTTP diagnostic endpoint. Detailed reports require application-level authorization; publicSummary() omits internal metadata.'),
            DiagnosticResult::passed('security.redaction', 'Credential-shaped text and sensitive detail keys are redacted; exception messages and traces are excluded from Core reports.'),
        ];
    }

    /** @return list<DiagnosticResult> */
    private function incidents(): array
    {
        $groups = [];
        foreach ($this->issues->all() as $issue) {
            $groups[$issue['code']][] = $issue;
        }
        if ($groups === []) {
            return [DiagnosticResult::passed('registration.integrity', 'No rejected registration mutations, duplicate IDs, recursive startup or observer failures were recorded.')];
        }
        ksort($groups);
        $rows = [];
        foreach ($groups as $code => $issues) {
            $rows[] = DiagnosticResult::warning($code, 'Core rejected or isolated an infrastructure operation; review startup integration.', ['count' => count($issues), 'components' => array_values(array_filter(array_column($issues, 'component'))), 'exceptions' => array_values(array_filter(array_column($issues, 'exception')))]);
        }

        return $rows;
    }
}
