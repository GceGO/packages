<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tests;

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
use Gcego\Navigation\Tests\Fixtures\Definitions;
use Gcego\Navigation\Tests\Fixtures\Livewire\Shell;
use Gcego\Navigation\Tests\Fixtures\Models\AccessRequest;
use Gcego\Navigation\Tests\Fixtures\Models\Student;
use Gcego\Navigation\Tests\Fixtures\Models\Subject;
use Gcego\Navigation\Tests\Fixtures\Models\User;
use Gcego\Navigation\Tests\Fixtures\Policies\Permissive;
use Gcego\Navigation\Tests\Fixtures\TenantResolver;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Security\SecurityServiceProvider;
use Gcego\Tables\TablesServiceProvider;
use Gcego\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint as Table;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use MallardDuck\LucideIcons\BladeLucideIconsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepare();
    }

    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class, ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, ActionsServiceProvider::class, FormsServiceProvider::class, BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class, BladeLucideIconsServiceProvider::class, LuminariesServiceProvider::class, TablesServiceProvider::class, InfolistsServiceProvider::class, WidgetsServiceProvider::class, LayoutsServiceProvider::class, NavigationServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('gcego-registry.discovery.enabled', false);
        $app['config']->set('gcego-registry.cache.enabled', false);
        $app['config']->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'models' => [Student::class => 'institution_id', Subject::class => 'institution_id', AccessRequest::class => 'institution_id']]);

        foreach ([Student::class, Subject::class, AccessRequest::class, User::class] as $model) {
            Gate::policy($model, Permissive::class);
        }
    }

    protected function defineRoutes($router): void
    {
        $page = static fn (): string => Blade::render('<x-gcego-navigation::navigation name="admin" :display="$display" /><x-gcego-navigation::breadcrumbs navigation="admin" />', ['display' => request()->query('display')]);

        Route::get('/admin', $page)->name('admin.dashboard');
        Route::get('/admin/students', $page)->name('admin.students.index');
        Route::get('/admin/students/{student}', $page)->name('admin.students.show');
        Route::get('/admin/subjects', $page)->name('admin.subjects.index');
        Route::get('/admin/subjects/{subject}', $page)->name('admin.subjects.show');
        Route::get('/admin/subjects/{subject}/edit', $page)->name('admin.subjects.edit');
        Route::get('/admin/topics', $page);
        Route::get('/admin/institutions', $page)->name('admin.institutions.index');
        Route::get('/admin/institutions/{institution}/members', $page)->name('admin.institutions.members');
        Route::get('/admin/requests', $page)->name('admin.requests.index');
        Route::get('/admin/reports/attendance', $page)->name('admin.reports.attendance');
        Route::get('/admin/reports/fees', $page);
        Route::get('/admin/settings', $page)->name('admin.settings');
        Route::middleware('web')->get('/admin/subjects/{subject}/live', static fn (): string => Blade::render('<livewire:navigation-shell />'))->name('admin.subjects.live');
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
        foreach (['subjects', 'access_requests', 'students'] as $name) {
            Schema::create($name, static function (Table $table): void {
                $table->id();
                $table->string('name')->default('');
                $table->unsignedInteger('institution_id')->default(0);
                $table->timestamps();
            });
        }

        Livewire::component('navigation-shell', Shell::class);
        $this->app?->make(Registry::class)->register(Definitions::admin());
    }
}
