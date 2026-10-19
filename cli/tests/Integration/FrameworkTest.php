<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * Framework-wide production checks across every installed GCEGO package.
 */
it('compiles every package view (as view:cache does)', function (): void {
    $compiler = app(BladeCompiler::class);
    $views = glob(dirname(__DIR__, 2).'/vendor/gcego/*/resources/views/{,*/,*/*/,*/*/*/}*.blade.php', GLOB_BRACE) ?: [];
    $problems = [];

    foreach ($views as $view) {
        try {
            PhpToken::tokenize($compiler->compileString((string) file_get_contents($view)), TOKEN_PARSE);
        } catch (Throwable $exception) {
            $problems[] = str_replace(dirname(__DIR__, 2).'/vendor/', '', $view).': '.$exception->getMessage();
        }
    }

    expect(count($views))->toBeGreaterThan(100)->and($problems)->toBe([]);

    // And Laravel's own view:cache over the registered package view paths.
    expect(Artisan::call('view:cache'))->toBe(0);
    Artisan::call('view:clear');
});

it('runs every package migration up and down on a fresh database, without table clashes', function (): void {
    $listing = static fn (): array => array_map(static fn (string $table): string => (string) preg_replace('/^main\./', '', $table), Schema::getTableListing());
    $tables = $listing();
    $migrations = glob(dirname(__DIR__, 2).'/vendor/gcego/*/database/migrations/*.php') ?: [];
    $instances = array_map(static fn (string $file): object => require $file, $migrations);

    // TestCase already ran them up: roll back in reverse, then up again.
    foreach (array_reverse($instances) as $migration) {
        $migration->down();
    }

    // Laravel's standard notifications table may be the application's own: rolling back only removes gcego's columns.
    $remaining = array_values(array_diff($listing(), ['users', 'memberships', 'subjects', 'sqlite_sequence', 'notifications']));
    expect($remaining)->toBe([])
        ->and(array_filter(Schema::getColumnListing('notifications'), static fn (string $column): bool => str_starts_with($column, 'gcego_')))->toBe([]);

    foreach ($instances as $migration) {
        $migration->up();
    }

    expect(count($migrations))->toBeGreaterThanOrEqual(6)->and($listing())->toEqualCanonicalizing($tables);
});
