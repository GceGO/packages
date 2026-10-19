<?php

declare(strict_types=1);

namespace Gcego\Pages\Tests;

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
use Gcego\Navigation\NavigationServiceProvider;
use Gcego\Pages\PagesServiceProvider;
use Gcego\Pages\Tests\Fixtures\Models\Document;
use Gcego\Pages\Tests\Fixtures\Models\Note;
use Gcego\Pages\Tests\Fixtures\Models\Subject;
use Gcego\Pages\Tests\Fixtures\Models\Topic;
use Gcego\Pages\Tests\Fixtures\Models\User;
use Gcego\Pages\Tests\Fixtures\PagesProvider;
use Gcego\Pages\Tests\Fixtures\Policies\Permissive;
use Gcego\Pages\Tests\Fixtures\Policies\SubjectPolicy;
use Gcego\Pages\Tests\Fixtures\TenantResolver;
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
    /** Who holds which permission (Gate abilities, gcego/security's "gate" permission provider). */
    public const array Permissions = [
        'subjects.view' => ['Admin', 'Editor', 'Reader', 'South'],
        'subjects.create' => ['Admin', 'Editor'],
        'subjects.update' => ['Admin', 'Editor', 'Reader'],
        'reports.view' => ['Admin'],
        'reports.finance' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepare();
    }

    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class, ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, ActionsServiceProvider::class, FormsServiceProvider::class, BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class, BladeLucideIconsServiceProvider::class, LuminariesServiceProvider::class, TablesServiceProvider::class, InfolistsServiceProvider::class, WidgetsServiceProvider::class, LayoutsServiceProvider::class, NavigationServiceProvider::class, PagesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('gcego-registry.discovery.enabled', false);
        $app['config']->set('gcego-registry.cache.enabled', false);
        $app['config']->set('gcego-registry.providers', [PagesProvider::class]);
        $app['config']->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'parameter' => 'institution', 'models' => [Subject::class => 'institution_id', Document::class => 'institution_id', Note::class => 'institution_id', Topic::class => 'institution_id']]);
        $app['config']->set('gcego-pages.routing.middleware', ['web', 'auth', 'gcego.tenant']);

        Gate::policy(Subject::class, SubjectPolicy::class);

        foreach ([Document::class, Note::class, Topic::class] as $model) {
            Gate::policy($model, Permissive::class);
        }

        foreach (self::Permissions as $permission => $names) {
            Gate::define($permission, static fn (User $user): bool => in_array($user->name, $names, true));
        }
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
            $table->string('status')->default('active');
            $table->text('internal_notes')->nullable();
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });
        Schema::create('documents', static function (Table $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->default('');
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });
        Schema::create('notes', static function (Table $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });
        Schema::create('topics', static function (Table $table): void {
            $table->string('slug')->primary();
            $table->string('name');
            $table->string('code')->default('');
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });
    }
}
