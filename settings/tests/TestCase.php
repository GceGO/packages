<?php

declare(strict_types=1);

namespace Gcego\Settings\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Gcego\Actions\ActionsServiceProvider;
use Gcego\Chroma\ChromaServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Data\DataServiceProvider;
use Gcego\Forms\FormsServiceProvider;
use Gcego\Infolists\InfolistsServiceProvider;
use Gcego\Layouts\LayoutsServiceProvider;
use Gcego\Luminaries\LuminariesServiceProvider;
use Gcego\Media\MediaServiceProvider;
use Gcego\Modules\ModulesServiceProvider;
use Gcego\Navigation\NavigationServiceProvider;
use Gcego\Notifications\NotificationsServiceProvider;
use Gcego\Pages\PagesServiceProvider;
use Gcego\Panels\PanelsServiceProvider;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Security\SecurityServiceProvider;
use Gcego\Settings\SettingsServiceProvider;
use Gcego\Settings\Tests\Fixtures\Models\User;
use Gcego\Settings\Tests\Fixtures\TenantResolver;
use Gcego\Tables\TablesServiceProvider;
use Gcego\Widgets\WidgetsServiceProvider;
use Gcego\Workflows\WorkflowsServiceProvider;
use Illuminate\Database\Schema\Blueprint as Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use MallardDuck\LucideIcons\BladeLucideIconsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Configuration applied on top of the defaults for the next application (see reboot()).
     *
     * @var array<string, mixed>
     */
    public static array $overrides = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepare();
    }

    protected function tearDown(): void
    {
        self::$overrides = [];

        parent::tearDown();
    }

    /**
     * A fresh application with these configuration values.
     *
     * @param  array<string, mixed>  $config
     */
    public function reboot(array $config): void
    {
        self::$overrides = $config;
        $this->refreshApplication();
        $this->prepare();
    }

    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class, ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, ActionsServiceProvider::class, FormsServiceProvider::class, BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class, BladeLucideIconsServiceProvider::class, LuminariesServiceProvider::class, TablesServiceProvider::class, InfolistsServiceProvider::class, WidgetsServiceProvider::class, LayoutsServiceProvider::class, NavigationServiceProvider::class, PagesServiceProvider::class, ModulesServiceProvider::class, PanelsServiceProvider::class, NotificationsServiceProvider::class, MediaServiceProvider::class, WorkflowsServiceProvider::class, SettingsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $config->set('app.url', 'http://localhost');
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('queue.default', 'sync');
        $config->set('mail.default', 'array');
        $config->set('auth.providers.users.model', User::class);
        $config->set('gcego-registry.discovery.enabled', true);
        $config->set('gcego-registry.discovery.roots', [['path' => __DIR__.'/Fixtures/Pages', 'kind' => 'page', 'entrypoint' => 'file']]);
        $config->set('gcego-registry.cache.enabled', false);
        $config->set('gcego-panels.discovery.paths', [__DIR__.'/Fixtures/Panels']);
        $config->set('gcego-modules.discovery.paths', []);
        $config->set('gcego-workflows.discovery.paths', [__DIR__.'/Fixtures/Workflows']);
        $config->set('gcego-settings.discovery.paths', [__DIR__.'/Fixtures/Settings']);
        $config->set('gcego-settings.discovery.pages', [__DIR__.'/Fixtures/SettingsPages']);
        $config->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'parameter' => 'institution', 'models' => []]);

        foreach (self::$overrides as $key => $value) {
            $config->set($key, $value);
        }

        Gate::define('use-desk', static fn (User $user): bool => true);
        Gate::define('gcego-settings.manage', static fn (User $user): bool => $user->name === 'Admin');
        Gate::define('gcego-settings.export', static fn (User $user): bool => $user->name === 'Admin');
        Gate::define('gcego-settings.import', static fn (User $user): bool => $user->name === 'Admin');
        Gate::define('security.manage', static fn (User $user): bool => $user->name === 'Security');
    }

    protected function defineRoutes($router): void
    {
        Route::get('/login', static fn (): string => 'Sign in')->name('login');
        Route::get('/profile', static fn (): string => 'Profile')->middleware('web')->name('profile');
        Route::post('/logout', static fn (): string => 'Signed out')->middleware('web')->name('logout');
    }

    private function prepare(): void
    {
        Schema::create('users', static function (Table $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->default('');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('memberships', static function (Table $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->unsignedInteger('institution_id');
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        $packages = dirname(__DIR__, 2);

        foreach ([...(glob($packages.'/notifications/database/migrations/*.php') ?: []), ...(glob($packages.'/media/database/migrations/*.php') ?: []), ...(glob($packages.'/workflows/database/migrations/*.php') ?: []), ...(glob(dirname(__DIR__).'/database/migrations/*.php') ?: [])] as $migration) {
            (require $migration)->up();
        }
    }
}
