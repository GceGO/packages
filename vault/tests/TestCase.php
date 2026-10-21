<?php

declare(strict_types=1);

namespace Gcego\Vault\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Gcego\Actions\ActionsServiceProvider;
use Gcego\Chroma\ChromaServiceProvider;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Data\DataServiceProvider;
use Gcego\Forms\FormsServiceProvider;
use Gcego\Infolists\InfolistsServiceProvider;
use Gcego\Layouts\LayoutsServiceProvider;
use Gcego\Luminaries\LuminariesServiceProvider;
use Gcego\Modules\ModulesServiceProvider;
use Gcego\Navigation\NavigationServiceProvider;
use Gcego\Pages\PagesServiceProvider;
use Gcego\Panels\PanelsServiceProvider;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Security\SecurityServiceProvider;
use Gcego\Tables\TablesServiceProvider;
use Gcego\Vault\Tests\Fixtures\Models\Book;
use Gcego\Vault\Tests\Fixtures\Models\User;
use Gcego\Vault\Tests\Fixtures\Policies\BookPolicy;
use Gcego\Vault\Tests\Fixtures\TenantResolver;
use Gcego\Vault\VaultServiceProvider;
use Gcego\Widgets\WidgetsServiceProvider;
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
        $app->register(ComponentServiceProvider::class);

        return [LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class, ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, ActionsServiceProvider::class, FormsServiceProvider::class, BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class, BladeLucideIconsServiceProvider::class, LuminariesServiceProvider::class, TablesServiceProvider::class, InfolistsServiceProvider::class, WidgetsServiceProvider::class, LayoutsServiceProvider::class, NavigationServiceProvider::class, PagesServiceProvider::class, ModulesServiceProvider::class, PanelsServiceProvider::class, VaultServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $config->set('app.url', 'http://localhost');
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('queue.default', 'sync');
        $config->set('auth.providers.users.model', User::class);
        $config->set('gcego-registry.discovery.enabled', true);
        $config->set('gcego-registry.discovery.roots', []);
        $config->set('gcego-registry.cache.enabled', false);
        $config->set('gcego-panels.discovery.paths', [__DIR__.'/Fixtures/Panels']);
        $config->set('gcego-modules.discovery.paths', [__DIR__.'/Fixtures/Modules']);
        $config->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'parameter' => 'institution', 'models' => []]);
        $config->set('gcego-vault.permissions.definitions', [
            'books.updateAny' => ['label' => 'Update anyone\'s books', 'group' => 'Library', 'requires' => ['books.update']],
            'reports.export' => ['label' => 'Export reports', 'group' => 'Reports', 'sensitive' => true, 'scope' => 'global'],
            'grades.update' => ['label' => 'Change grades', 'group' => 'Records', 'scope' => 'tenant'],
        ]);

        foreach (self::$overrides as $key => $value) {
            $config->set($key, $value);
        }

        Gate::policy(Book::class, BookPolicy::class);
        Gate::define('legacy.ability', static fn (User $user): bool => $user->name === 'Legacy');
    }

    protected function defineRoutes($router): void
    {
        Route::get('/login', static fn (): string => 'Sign in')->name('login');
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
            $table->timestamps();
        });
        Schema::create('books', static function (Table $table): void {
            $table->id();
            $table->string('title');
            $table->string('isbn')->default('');
            $table->foreignId('owner_id')->nullable();
            $table->timestamps();
        });

        foreach (glob(dirname(__DIR__).'/database/migrations/*.php') ?: [] as $migration) {
            (require $migration)->up();
        }
    }
}
