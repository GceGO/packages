<?php

declare(strict_types=1);

namespace Gcego\Modules\Tests;

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
use Gcego\Modules\ModulesServiceProvider;
use Gcego\Modules\Tests\Fixtures\Models\Subject;
use Gcego\Modules\Tests\Fixtures\Models\Tag;
use Gcego\Modules\Tests\Fixtures\Models\Topic;
use Gcego\Modules\Tests\Fixtures\Models\User;
use Gcego\Modules\Tests\Fixtures\Policies\RecordPolicy;
use Gcego\Modules\Tests\Fixtures\Policies\SubjectPolicy;
use Gcego\Modules\Tests\Fixtures\TenantResolver;
use Gcego\Navigation\NavigationServiceProvider;
use Gcego\Pages\PagesServiceProvider;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Security\SecurityServiceProvider;
use Gcego\Tables\TablesServiceProvider;
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
     * A fresh application with these configuration values (routes are
     * registered at boot from the registry, so module changes need a new application).
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
        return [LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class, ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, ActionsServiceProvider::class, FormsServiceProvider::class, BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class, BladeLucideIconsServiceProvider::class, LuminariesServiceProvider::class, TablesServiceProvider::class, InfolistsServiceProvider::class, WidgetsServiceProvider::class, LayoutsServiceProvider::class, NavigationServiceProvider::class, PagesServiceProvider::class, ModulesServiceProvider::class];
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
        $config->set('auth.providers.users.model', User::class);
        $config->set('gcego-registry.discovery.enabled', true);
        $config->set('gcego-registry.discovery.roots', []);
        $config->set('gcego-registry.cache.enabled', false);
        $config->set('gcego-modules.discovery.paths', [__DIR__.'/Fixtures/Modules']);
        $config->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'parameter' => 'institution', 'models' => [Subject::class => 'institution_id', Topic::class => 'institution_id', Tag::class => 'institution_id']]);
        $config->set('gcego-pages.routing.middleware', ['web', 'auth', 'gcego.tenant']);

        foreach (self::$overrides as $key => $value) {
            $config->set($key, $value);
        }

        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::policy(Topic::class, RecordPolicy::class);
        Gate::policy(Tag::class, RecordPolicy::class);
        Gate::define('academics.reports', static fn (User $user): bool => $user->name === 'Admin');
    }

    protected function defineRoutes($router): void
    {
        Route::get('/login', static fn (): string => 'Sign in')->name('login');
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
        Schema::create('subjects', static function (Table $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->default('');
            $table->text('internal_notes')->nullable();
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });
        Schema::create('topics', static function (Table $table): void {
            $table->id();
            $table->foreignId('subject_id')->nullable();
            $table->string('name');
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });
        Schema::create('tags', static function (Table $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });
        Schema::create('subject_tag', static function (Table $table): void {
            $table->foreignId('subject_id');
            $table->foreignId('tag_id');
        });
        Schema::create('notes', static function (Table $table): void {
            $table->id();
            $table->string('body')->default('');
            $table->timestamps();
        });
    }
}
