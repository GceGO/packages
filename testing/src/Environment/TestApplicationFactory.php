<?php

declare(strict_types=1);

namespace Gcego\Testing\Environment;

use Gcego\Testing\TestingServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;

/**
 * The service providers and configuration an isolated GCEGO test
 * application needs, following the conventions of the packages' own
 * suites: in-memory SQLite, array cache and session, sync queue, array
 * mail, registry discovery and its cache off (definitions are registered
 * explicitly), and a per-process cache prefix for parallel runs.
 *
 * Use it from Orchestra Testbench (PackageTestCase does) or call
 * configure() from your own defineEnvironment().
 */
final class TestApplicationFactory
{
    /**
     * The GCEGO providers in dependency order. Packages that are not
     * installed are left out (Composer guarantees their dependants are too).
     * gcego/sentinel is not included: it registers the authentication routes
     * and needs its tables (and laravel/passkeys' provider for passkeys) — add
     * it through gcegoProviders() when testing it.
     */
    public const array Providers = [
        'Livewire\LivewireServiceProvider',
        'Gcego\Components\ComponentServiceProvider',
        'Gcego\Core\GcegoServiceProvider',
        'Gcego\Registry\RegistryServiceProvider',
        'Gcego\Blueprints\BlueprintsServiceProvider',
        'Gcego\Runtime\RuntimeServiceProvider',
        'Gcego\Rendering\RenderingServiceProvider',
        'Gcego\Chroma\ChromaServiceProvider',
        'Gcego\Data\DataServiceProvider',
        'Gcego\Security\SecurityServiceProvider',
        'Gcego\Actions\ActionsServiceProvider',
        'Gcego\Forms\FormsServiceProvider',
        'Gcego\Luminaries\LuminariesServiceProvider',
        'Gcego\Tables\TablesServiceProvider',
        'Gcego\Infolists\InfolistsServiceProvider',
        'Gcego\Widgets\WidgetsServiceProvider',
        'Gcego\Layouts\LayoutsServiceProvider',
        'Gcego\Navigation\NavigationServiceProvider',
        'Gcego\Pages\PagesServiceProvider',
        'Gcego\Modules\ModulesServiceProvider',
        'Gcego\Panels\PanelsServiceProvider',
        'Gcego\Notifications\NotificationsServiceProvider',
        'Gcego\Media\MediaServiceProvider',
        'Gcego\Workflows\WorkflowsServiceProvider',
        'Gcego\Settings\SettingsServiceProvider',
        'Gcego\Analytics\AnalyticsServiceProvider',
        'Gcego\Studio\StudioServiceProvider',
        'Gcego\Cli\CliServiceProvider',
    ];

    /**
     * @param  list<class-string<ServiceProvider>>|null  $only  Restrict to these (still in dependency order).
     * @return list<class-string<ServiceProvider>>
     */
    public static function providers(?array $only = null): array
    {
        $providers = [];

        foreach (self::Providers as $provider) {
            if (! class_exists($provider)) {
                continue;
            }

            if ($only === null || in_array($provider, $only, true)) {
                /** @var class-string<ServiceProvider> $provider */
                $providers[] = $provider;
            }
        }

        $providers[] = TestingServiceProvider::class;

        return $providers;
    }

    /**
     * The configuration values applied by configure().
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $token = getenv('TEST_TOKEN');

        return [
            'app.key' => 'base64:'.base64_encode(str_repeat('g', 32)),
            'app.url' => 'http://localhost',
            'app.timezone' => 'UTC',
            'database.default' => 'testing',
            'database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'cache.default' => 'array',
            'cache.prefix' => 'gcego_testing_'.(is_string($token) && $token !== '' ? $token : '0').'_',
            'session.driver' => 'array',
            'queue.default' => 'sync',
            'mail.default' => 'array',
            'broadcasting.default' => 'null',
            'gcego-registry.discovery.enabled' => false,
            'gcego-registry.cache.enabled' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides  Applied after the defaults.
     */
    public static function configure(Repository $config, array $overrides = []): void
    {
        foreach ([...self::defaults(), ...$overrides] as $key => $value) {
            $config->set($key, $value);
        }
    }
}
