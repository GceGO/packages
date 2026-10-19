<?php

declare(strict_types=1);

namespace Gcego\Cli\Diagnostics;

use Composer\InstalledVersions;
use Gcego\Analytics\Diagnostics\AnalyticsInspector;
use Gcego\Chroma\Diagnostics\ChromaInspector;
use Gcego\Cli\CliManager;
use Gcego\Cli\Commands\Concerns\CommandNames;
use Gcego\Cli\Validation\DefinitionValidation;
use Gcego\Registry\RegistryConfig;
use Gcego\Runtime\Diagnostics\RuntimeInspector;
use Gcego\Settings\SettingsManager;
use Gcego\Studio\Health\HealthCheckResult;
use Gcego\Studio\Health\HealthStatus;
use Gcego\Studio\Health\StudioHealthManager;
use Gcego\Workflows\Diagnostics\WorkflowInspector;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * gcego:doctor — every check is read-only:
 *
 *  - gcego/studio's health checks (platform, packages, core, configuration,
 *    registry, routes, authentication, panel access, tenancy, assets,
 *    storage; with --production its readiness checks), reused as they are;
 *  - the CLI's own: runtime renderers, styling, definition validation,
 *    cache, queue, scheduler, settings, analytics, workflows, testing
 *    toolkit, command names, and with --database the connection and
 *    pending migrations (nothing is migrated);
 *  - checks contributed by packages (CliManager::check()).
 *
 * A check that throws is reported as its own failure; the others still run.
 */
final readonly class Doctor
{
    /** Built-in CLI check ids. */
    public const array Checks = ['discovery.roots', 'runtime.renderers', 'styling.themes', 'definitions.valid', 'cache.store', 'queue.connection', 'scheduler.tasks', 'settings.definitions', 'analytics.definitions', 'workflows.definitions', 'testing.toolkit', 'cli.commands', 'database.connection', 'database.migrations'];

    public function __construct(private Application $app, private Repository $config, private CliManager $cli) {}

    public function run(bool $database = false, bool $production = false): DiagnosticReport
    {
        $results = [];

        try {
            foreach ($this->app->make(StudioHealthManager::class)->run($production)->results as $result) {
                $results[] = $this->fromStudio($result);
            }
        } catch (Throwable $exception) {
            $results[] = DiagnosticResult::failure('studio.health', 'Studio health', 'gcego/studio\'s health checks could not run: '.$exception->getMessage(), 'Run php artisan gcego:studio:health for details.', 'gcego/studio');
        }

        $checks = [
            'discovery.roots' => fn (): DiagnosticResult => $this->roots(),
            'runtime.renderers' => fn (): DiagnosticResult => $this->renderers(),
            'styling.themes' => fn (): DiagnosticResult => $this->styling(),
            'definitions.valid' => fn (): DiagnosticResult => $this->definitions(),
            'cache.store' => fn (): DiagnosticResult => $this->cache($production),
            'queue.connection' => fn (): DiagnosticResult => $this->queue($production),
            'scheduler.tasks' => fn (): DiagnosticResult => $this->scheduler(),
            'settings.definitions' => fn (): DiagnosticResult => $this->packageDefinitions('settings.definitions', 'Settings', 'gcego/settings', fn (): array => $this->settingsProblems()),
            'analytics.definitions' => fn (): DiagnosticResult => $this->packageDefinitions('analytics.definitions', 'Analytics', 'gcego/analytics', fn (): array => $this->analyticsProblems()),
            'workflows.definitions' => fn (): DiagnosticResult => $this->packageDefinitions('workflows.definitions', 'Workflows', 'gcego/workflows', fn (): array => $this->workflowProblems()),
            'testing.toolkit' => fn (): DiagnosticResult => $this->testing(),
            'cli.commands' => fn (): DiagnosticResult => $this->commands(),
            'database.connection' => fn (): DiagnosticResult => $database ? $this->connection() : DiagnosticResult::skipped('database.connection', 'Database connection', 'Not checked: pass --database to connect.'),
            'database.migrations' => fn (): DiagnosticResult => $database ? $this->migrations() : DiagnosticResult::skipped('database.migrations', 'Migrations', 'Not checked: pass --database to read the migrations table.'),
        ];

        foreach ($checks as $id => $check) {
            $results[] = $this->safely($id, $id, $check);
        }

        foreach ($this->cli->checks() as $check) {
            $results[] = $check->usesDatabase() && ! $database
                ? DiagnosticResult::skipped($check->id(), $check->label(), 'Not checked: it needs --database.')
                : $this->safely($check->id(), $check->label(), static fn (): DiagnosticResult => $check->run());
        }

        return new DiagnosticReport($results);
    }

    /**
     * @param  \Closure(): DiagnosticResult  $check
     */
    private function safely(string $id, string $label, \Closure $check): DiagnosticResult
    {
        try {
            return $check();
        } catch (Throwable $exception) {
            return DiagnosticResult::failure($id, $label, sprintf('The check failed: %s (%s).', $exception->getMessage(), class_basename($exception)), 'Fix the error above; the other checks ran.');
        }
    }

    private function fromStudio(HealthCheckResult $result): DiagnosticResult
    {
        $status = match ($result->status) {
            HealthStatus::Pass => DiagnosticStatus::Pass,
            HealthStatus::Warning => DiagnosticStatus::Warning,
            HealthStatus::Failure => DiagnosticStatus::Failure,
            HealthStatus::Skipped => DiagnosticStatus::Skipped,
        };

        return new DiagnosticResult($result->id, $result->label, $status, $result->message, $result->remediation, $result->package ?? 'gcego/studio');
    }

    /**
     * gcego/registry discovers each file once: when two kinds share a folder with the
     * same entrypoint rule, every file goes to the first kind and the second finds nothing.
     */
    private function roots(): DiagnosticResult
    {
        $this->app->make(RegistryConfig::class);
        $roots = $this->config->get('gcego-registry.discovery.roots', []);
        $rules = [];

        foreach (is_array($roots) ? $roots : [] as $root) {
            if (is_array($root) && is_string($root['path'] ?? null) && is_string($root['kind'] ?? null)) {
                $path = realpath($root['path']) ?: rtrim($root['path'], '/');
                $entrypoint = is_string($root['entrypoint'] ?? null) ? $root['entrypoint'] : 'directory';
                $rules[$path."\0".$entrypoint][] = $root['kind'];
            }
        }

        $shadowed = array_filter($rules, static fn (array $kinds): bool => count(array_unique($kinds)) > 1);

        if ($shadowed === []) {
            return DiagnosticResult::pass('discovery.roots', 'Discovery roots', sprintf('%d discovery roots; no two kinds compete for the same files.', count($rules)), 'gcego/registry');
        }

        $messages = [];

        foreach ($shadowed as $key => $kinds) {
            [$path, $entrypoint] = explode("\0", $key);
            $messages[] = sprintf('%s (entrypoint "%s") is configured for %s; its files are only discovered as "%s"', str_replace($this->app->basePath().'/', '', $path), $entrypoint, implode(' and ', array_unique($kinds)), $kinds[0]);
        }

        return DiagnosticResult::failure('discovery.roots', 'Discovery roots', implode('; ', $messages).'.', 'Give one of the kinds another folder or entrypoint rule in gcego-registry.discovery.roots.', 'gcego/registry');
    }

    private function renderers(): DiagnosticResult
    {
        $report = $this->app->make(RuntimeInspector::class)->inspect();
        $errors = array_filter($report->diagnostics, static fn ($diagnostic): bool => $diagnostic->level === 'error');

        return $errors === [] && $report->missingRenderers === []
            ? DiagnosticResult::pass('runtime.renderers', 'Runtime renderers', sprintf('%d renderer bindings are registered.', count($report->renderers)), 'gcego/runtime')
            : DiagnosticResult::failure('runtime.renderers', 'Runtime renderers', implode(' ', array_map(static fn ($diagnostic): string => $diagnostic->message, $errors)) ?: sprintf('%d component types have no renderer.', count($report->missingRenderers)), 'Install the package that renders them, or register a renderer.', 'gcego/runtime');
    }

    private function styling(): DiagnosticResult
    {
        $report = $this->app->make(ChromaInspector::class)->inspect();
        $errors = array_values(array_filter($report->diagnostics, static fn ($diagnostic): bool => $diagnostic->level === 'error'));
        $warnings = array_values(array_filter($report->diagnostics, static fn ($diagnostic): bool => $diagnostic->level === 'warning'));

        return match (true) {
            $errors !== [] => DiagnosticResult::failure('styling.themes', 'Styling', implode(' ', array_map(static fn ($diagnostic): string => $diagnostic->message, $errors)), 'Check gcego-chroma.php.', 'gcego/chroma'),
            $warnings !== [] => DiagnosticResult::warning('styling.themes', 'Styling', implode(' ', array_map(static fn ($diagnostic): string => $diagnostic->message, $warnings)), 'Check gcego-chroma.php.', 'gcego/chroma'),
            default => DiagnosticResult::pass('styling.themes', 'Styling', 'The configured themes and slots are valid.', 'gcego/chroma'),
        };
    }

    private function definitions(): DiagnosticResult
    {
        $report = $this->app->make(DefinitionValidation::class)->run();
        $invalid = $report->invalid();

        return $invalid === []
            ? DiagnosticResult::pass('definitions.valid', 'Definitions', sprintf('%d definitions are valid.', count($report->entries)), 'gcego/registry')
            : DiagnosticResult::failure('definitions.valid', 'Definitions', sprintf('%d of %d definitions are invalid: %s.', count($invalid), count($report->entries), implode(', ', array_slice(array_map(static fn ($entry): string => $entry->identity ?? $entry->source, $invalid), 0, 10))), 'Run php artisan gcego:validate for the problems.', 'gcego/registry');
    }

    private function cache(bool $production): DiagnosticResult
    {
        $store = $this->text('cache.default');
        $driver = $this->text('cache.stores.'.$store.'.driver');

        return $driver === 'array' && ($production || $this->app->environment('production'))
            ? DiagnosticResult::warning('cache.store', 'Cache', sprintf('The cache store "%s" uses the array driver: registry, widget and analytics caches last one request.', $store), 'Use redis, memcached, database or file in production (CACHE_STORE).', 'laravel/framework')
            : DiagnosticResult::pass('cache.store', 'Cache', sprintf('Cache store "%s" (%s driver).', $store, $driver), 'laravel/framework');
    }

    private function queue(bool $production): DiagnosticResult
    {
        $connection = $this->text('queue.default');
        $driver = $this->text('queue.connections.'.$connection.'.driver');
        $queued = array_filter(['gcego/notifications', 'gcego/workflows', 'gcego/analytics', 'gcego/media'], fn (string $package): bool => $this->installed($package));

        if ($queued === []) {
            return DiagnosticResult::notApplicable('queue.connection', 'Queue', 'No installed GCEGO package queues work.');
        }

        return $driver === 'sync' && ($production || $this->app->environment('production'))
            ? DiagnosticResult::warning('queue.connection', 'Queue', sprintf('The queue "%s" is synchronous: %s run their jobs inside requests.', $connection, implode(', ', $queued)), 'Use a queue driver (database, redis, sqs) and run php artisan queue:work.', 'laravel/framework')
            : DiagnosticResult::pass('queue.connection', 'Queue', sprintf('Queue connection "%s" (%s driver).', $connection, $driver), 'laravel/framework');
    }

    private function scheduler(): DiagnosticResult
    {
        $needed = array_filter([
            'gcego:workflows:process-deadlines' => $this->installed('gcego/workflows'),
            'gcego:analytics:run-schedules' => $this->installed('gcego/analytics'),
        ]);

        if ($needed === []) {
            return DiagnosticResult::notApplicable('scheduler.tasks', 'Scheduler', 'No installed GCEGO package needs scheduled tasks.');
        }

        $this->app->make(Kernel::class)->bootstrap();
        $commands = implode("\n", array_map(static fn ($event): string => (string) $event->command, $this->app->make(Schedule::class)->events()));
        $missing = array_values(array_filter(array_keys($needed), static fn (string $command): bool => ! str_contains($commands, $command)));

        return $missing === []
            ? DiagnosticResult::pass('scheduler.tasks', 'Scheduler', 'The GCEGO scheduled tasks are scheduled.')
            : DiagnosticResult::warning('scheduler.tasks', 'Scheduler', sprintf('Not scheduled: %s.', implode(', ', $missing)), sprintf('Add Schedule::command(\'%s\')->everyMinute(); to routes/console.php and run the scheduler (php artisan schedule:work or cron).', $missing[0]));
    }

    /**
     * @param  \Closure(): list<string>  $problems
     */
    private function packageDefinitions(string $id, string $label, string $package, \Closure $problems): DiagnosticResult
    {
        if (! $this->installed($package)) {
            return DiagnosticResult::notApplicable($id, $label, sprintf('%s is not installed.', $package), $package);
        }

        $found = $problems();

        return $found === []
            ? DiagnosticResult::pass($id, $label, sprintf('%s definitions are consistent.', $label), $package)
            : DiagnosticResult::failure($id, $label, implode(' ', array_slice($found, 0, 5)), sprintf('Run the %s inspector for details.', $package), $package);
    }

    /**
     * @return list<string>
     */
    private function settingsProblems(): array
    {
        $report = $this->app->make(SettingsManager::class)->inspect(false);

        $problems = is_array($report['problems'] ?? null) ? $report['problems'] : [];

        return array_values(array_map(static fn (mixed $problem): string => is_string($problem) ? $problem : (string) json_encode($problem), $problems));
    }

    /**
     * @return list<string>
     */
    private function analyticsProblems(): array
    {
        $report = $this->app->make(AnalyticsInspector::class)->report(false);
        $problems = [];

        foreach (is_array($report['definitions'] ?? null) ? $report['definitions'] : [] as $kind => $definitions) {
            foreach (is_array($definitions) ? $definitions : [] as $definition) {
                foreach (is_array($definition['problems'] ?? null) ? $definition['problems'] : [] as $problem) {
                    $problems[] = sprintf('%s:%s: %s', (string) $kind, is_string($definition['name'] ?? null) ? $definition['name'] : '?', is_string($problem) ? $problem : '');
                }
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function workflowProblems(): array
    {
        $report = $this->app->make(WorkflowInspector::class)->report(false);
        $problems = [];

        foreach (is_array($report['workflows'] ?? null) ? $report['workflows'] : [] as $workflow) {
            foreach (is_array($workflow['problems'] ?? null) ? $workflow['problems'] : [] as $problem) {
                $problems[] = sprintf('workflow:%s: %s', is_string($workflow['name'] ?? null) ? $workflow['name'] : '?', is_string($problem) ? $problem : '');
            }
        }

        return $problems;
    }

    private function testing(): DiagnosticResult
    {
        $runner = $this->installed('pestphp/pest') ? 'Pest' : ($this->installed('phpunit/phpunit') ? 'PHPUnit' : null);

        return match (true) {
            ! $this->installed('gcego/testing') => DiagnosticResult::warning('testing.toolkit', 'Testing toolkit', 'gcego/testing is not installed: gcego:make-test and GcegoTest assertions are unavailable.', 'composer require --dev gcego/testing', 'gcego/testing'),
            $runner === null => DiagnosticResult::warning('testing.toolkit', 'Testing toolkit', 'No test runner (Pest or PHPUnit) is installed.', 'composer require --dev pestphp/pest', 'gcego/testing'),
            default => DiagnosticResult::pass('testing.toolkit', 'Testing toolkit', sprintf('gcego/testing with %s.', $runner), 'gcego/testing'),
        };
    }

    private function commands(): DiagnosticResult
    {
        $kernel = $this->app->make(Kernel::class);
        $all = $kernel->all();
        $collisions = [];

        foreach (CommandNames::Commands as $name => $class) {
            if (isset($all[$name]) && ! $all[$name] instanceof $class) {
                $collisions[] = sprintf('%s is %s, not %s', $name, $all[$name]::class, $class);
            }
        }

        return $collisions === []
            ? DiagnosticResult::pass('cli.commands', 'CLI commands', sprintf('%d gcego:* commands are registered, none shadowed.', count(array_filter(array_keys($all), static fn (string $name): bool => str_starts_with($name, 'gcego:')))), 'gcego/cli')
            : DiagnosticResult::failure('cli.commands', 'CLI commands', 'Command names collide: '.implode('; ', $collisions).'.', 'Rename the other command: gcego/cli owns these names.', 'gcego/cli');
    }

    private function connection(): DiagnosticResult
    {
        $name = $this->text('database.default');

        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            return DiagnosticResult::failure('database.connection', 'Database connection', sprintf('The "%s" connection failed (%s).', $name, class_basename($exception)), 'Check the DB_* settings in .env.', 'laravel/framework');
        }

        return DiagnosticResult::pass('database.connection', 'Database connection', sprintf('Connected to "%s" (%s).', $name, $this->text('database.connections.'.$name.'.driver')), 'laravel/framework');
    }

    private function migrations(): DiagnosticResult
    {
        $migrator = $this->app->make('migrator');
        assert($migrator instanceof Migrator);

        if (! $migrator->repositoryExists()) {
            return DiagnosticResult::failure('database.migrations', 'Migrations', 'The migrations table does not exist.', 'Run php artisan migrate.', 'laravel/framework');
        }

        $files = $migrator->getMigrationFiles([$this->app->databasePath('migrations'), ...$migrator->paths()]);
        $pending = array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));

        return $pending === []
            ? DiagnosticResult::pass('database.migrations', 'Migrations', sprintf('All %d migrations have run.', count($files)), 'laravel/framework')
            : DiagnosticResult::warning('database.migrations', 'Migrations', sprintf('%d migrations have not run: %s.', count($pending), implode(', ', array_slice($pending, 0, 5))), 'Review them, then run php artisan migrate (the doctor never migrates).', 'laravel/framework');
    }

    private function text(string $key): string
    {
        $value = $this->config->get($key);

        return is_scalar($value) ? (string) $value : '';
    }

    private function installed(string $package): bool
    {
        try {
            return InstalledVersions::isInstalled($package);
        } catch (Throwable) {
            return false;
        }
    }
}
