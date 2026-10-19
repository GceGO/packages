<?php

declare(strict_types=1);

use Gcego\Cli\CliManager;
use Gcego\Cli\Contracts\DiagnosticCheck;
use Gcego\Cli\Diagnostics\DiagnosticResult;
use Gcego\Cli\Diagnostics\Doctor;
use Gcego\Cli\ExitCode;
use Illuminate\Support\Facades\Schema;

function check(string $id, Closure $run, bool $database = false): DiagnosticCheck
{
    return new class($id, $run, $database) implements DiagnosticCheck
    {
        public function __construct(private string $name, private Closure $callback, private bool $database) {}

        public function id(): string
        {
            return $this->name;
        }

        public function label(): string
        {
            return ucfirst($this->name);
        }

        public function usesDatabase(): bool
        {
            return $this->database;
        }

        public function run(): DiagnosticResult
        {
            return ($this->callback)();
        }
    };
}

it('runs Studio\'s health checks and its own, read-only', function (): void {
    $files = glob(ws('*')) ?: [];
    $report = app(Doctor::class)->run();
    $statuses = collect($report->results)->mapWithKeys(fn ($result): array => [$result->id => $result->status->value]);

    expect($statuses->keys()->all())->toContain('platform.compatibility', 'core.boot', 'routes.panels', 'discovery.roots', 'runtime.renderers', 'styling.themes', 'definitions.valid', 'testing.toolkit', 'cli.commands')
        ->and($statuses['discovery.roots'])->toBe('pass')->and($statuses['runtime.renderers'])->toBe('pass')->and($statuses['testing.toolkit'])->toBe('pass')
        ->and($statuses['database.connection'])->toBe('skipped')->and($statuses['scheduler.tasks'])->toBe('pass')
        ->and(glob(ws('*')) ?: [])->toBe($files);
});

it('accepts the default shared Modules folder, and fails two kinds competing for the same files', function (): void {
    // gcego/registry's default: blueprints (directory rule) and gcego/modules (Module.php) share app/GCEGo/Modules.
    $this->reboot(['gcego-registry.discovery.roots' => [['path' => ws('app/GCEGo/Modules'), 'kind' => 'blueprint', 'entrypoint' => 'directory']]]);
    @mkdir(ws('app/GCEGo/Modules'), 0777, true);
    expect(app(Doctor::class)->run()->result('discovery.roots')?->status->value)->toBe('pass');

    gcego('gcego:make-module', ['name' => 'Academics']);
    gcego('gcego:make-blueprint', ['name' => 'Subjects']);
    expect(gcegoJson('gcego:list')['lists'])->toMatchArray(['modules' => ['total' => 1, 'items' => ['module:academics']], 'blueprints' => ['total' => 1, 'items' => ['blueprint:subjects']]]);

    $this->reboot(['gcego-registry.discovery.roots' => [['path' => ws('app/GCEGo/Pages'), 'kind' => 'page', 'entrypoint' => 'file'], ['path' => ws('app/GCEGo/Pages'), 'kind' => 'layout', 'entrypoint' => 'file']]]);
    @mkdir(ws('app/GCEGo/Pages'), 0777, true);
    $result = app(Doctor::class)->run()->result('discovery.roots');

    expect($result?->status->value)->toBe('failure')->and($result?->message)->toContain('is configured for page and layout; its files are only discovered as "page"');
});

it('checks the database only on request, without migrating', function (): void {
    $report = app(Doctor::class)->run(database: true);

    expect($report->result('database.connection')?->status->value)->toBe('pass')
        ->and($report->result('database.migrations')?->status->value)->toBe('failure')
        ->and($report->result('database.migrations')?->remediation)->toBe('Run php artisan migrate.')
        ->and(Schema::hasTable('migrations'))->toBeFalse();
});

it('runs custom checks in isolation and exits 6 on failure', function (): void {
    $cli = app(CliManager::class);
    $cli->check(check('acme.ok', fn () => DiagnosticResult::pass('acme.ok', 'Ok', 'Fine.')));
    $cli->check(check('acme.broken', fn () => throw new RuntimeException('Directory unreachable.')));
    $cli->check(check('acme.db', fn () => DiagnosticResult::pass('acme.db', 'Db', 'Fine.'), database: true));

    $report = gcegoJson('gcego:doctor');
    $byId = collect($report['checks'])->keyBy('id');

    expect($byId['acme.ok']['status'])->toBe('pass')
        ->and($byId['acme.broken']['status'])->toBe('failure')->and($byId['acme.broken']['message'])->toContain('Directory unreachable.')
        ->and($byId['acme.db']['status'])->toBe('skipped')
        ->and($report['healthy'])->toBeFalse()->and($report['exit_code'])->toBe(ExitCode::DiagnosticFailure);

    [$code, $output] = gcego('gcego:doctor');
    expect($code)->toBe(ExitCode::DiagnosticFailure)->and($output)->toContain('GCEGO ADMIN STUDIO — SYSTEM DIAGNOSTICS')->toContain('UNHEALTHY');
});

it('adds production readiness checks and warns about array caches and sync queues there', function (): void {
    $this->reboot(['app.debug' => true]);
    $report = app(Doctor::class)->run(production: true);

    expect($report->result('production.debug')?->status->value)->toBe('failure')
        ->and($report->result('cache.store')?->status->value)->toBe('warning')
        ->and($report->result('queue.connection')?->status->value)->toBe('warning');
});

it('never prints secrets', function (): void {
    $this->reboot(['database.connections.testing.password' => 'hunter2-db', 'app.key' => 'base64:'.base64_encode(str_repeat('s', 32))]);

    foreach (['gcego:doctor', 'gcego:about', 'gcego:packages', 'gcego:cache-status', 'gcego:registry'] as $command) {
        foreach (['text', 'json'] as $format) {
            [, $output] = gcego($command, ['--format' => $format, ...($command === 'gcego:doctor' ? ['--database' => true] : [])]);
            expect($output)->not->toContain('hunter2-db')->not->toContain(base64_encode(str_repeat('s', 32)));
        }
    }
});
