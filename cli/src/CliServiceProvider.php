<?php

declare(strict_types=1);

namespace Gcego\Cli;

use Gcego\Cli\Commands\Concerns\CommandNames;
use Gcego\Cli\Diagnostics\Doctor;
use Gcego\Cli\Generation\NamespaceResolver;
use Gcego\Cli\Generation\PathResolver;
use Gcego\Cli\Inspection\Catalog;
use Gcego\Cli\Output\Redactor;
use Gcego\Cli\Stubs\StubManager;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for gcego/cli; also the "gcego/cli" GCEGO package.
 *
 * register(): configuration and services — singletons built on first use,
 * so web requests pay nothing; no query, no file write.
 * boot(): in the console only, the gcego:* commands (CommandNames) and
 * publishing of the configuration and stubs.
 */
final class CliServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Name = 'gcego-cli';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-cli.php', self::Name);

        $this->app->singleton(CliConfig::class, static function (Application $app): CliConfig {
            $config = $app->make(Repository::class)->get(self::Name, []);

            return CliConfig::fromArray(is_array($config) ? $config : []);
        });
        $this->app->singleton(PathResolver::class, static fn (Application $app): PathResolver => new PathResolver($app->basePath()));
        $this->app->singleton(Redactor::class, static fn (Application $app): Redactor => new Redactor($app->basePath()));
        $this->app->singleton(NamespaceResolver::class);
        $this->app->singleton(StubManager::class, static fn (Application $app): StubManager => new StubManager(dirname(__DIR__).'/stubs', $app->make(CliConfig::class)->stubPath));
        // Not a singleton: each command (also several in one process, e.g. Artisan::call()) sees the files as they are.
        $this->app->bind(Catalog::class);
        $this->app->singleton(CliManager::class, static function (Application $app): CliManager {
            $manager = new CliManager($app, $app->make(CliConfig::class));
            $manager->reserveChecks(Doctor::Checks);

            return $manager;
        });
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $root = dirname(__DIR__);
        $this->publishes([$root.'/config/gcego-cli.php' => $this->app->configPath('gcego-cli.php')], 'gcego-cli-config');
        $this->publishes([$root.'/stubs' => $this->app->basePath('stubs/gcego')], 'gcego-cli-stubs');

        if ($this->app->make(CliConfig::class)->enabled) {
            $this->commands(array_values(CommandNames::Commands));
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/cli',
            requires: ['gcego/core', 'gcego/registry', 'gcego/studio'],
            capabilities: ['cli', 'cli.generators', 'cli.inspection', 'cli.diagnostics'],
            description: 'The GCEGO developer CLI: generators, inspection, validation and diagnostics.',
        );
    }
}
