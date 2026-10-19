<?php

declare(strict_types=1);

namespace Gcego\Widgets\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Gcego\Actions\ActionsServiceProvider;
use Gcego\Chroma\ChromaServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Data\DataServiceProvider;
use Gcego\Infolists\InfolistsServiceProvider;
use Gcego\Luminaries\LuminariesServiceProvider;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Security\SecurityServiceProvider;
use Gcego\Tables\TablesServiceProvider;
use Gcego\Widgets\Tests\Fixtures\Models\ActivityLog;
use Gcego\Widgets\Tests\Fixtures\Models\Payment;
use Gcego\Widgets\Tests\Fixtures\Models\Result;
use Gcego\Widgets\Tests\Fixtures\Models\Student;
use Gcego\Widgets\Tests\Fixtures\Models\User;
use Gcego\Widgets\Tests\Fixtures\Policies\FinancePolicy;
use Gcego\Widgets\Tests\Fixtures\Policies\Permissive;
use Gcego\Widgets\Tests\Fixtures\TenantResolver;
use Gcego\Widgets\Tests\Fixtures\Widgets;
use Gcego\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint as Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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
        return [LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class, ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, ActionsServiceProvider::class, BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class, BladeLucideIconsServiceProvider::class, LuminariesServiceProvider::class, TablesServiceProvider::class, InfolistsServiceProvider::class, WidgetsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.timezone', 'UTC');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('gcego-registry.discovery.enabled', false);
        $app['config']->set('gcego-registry.cache.enabled', false);
        $app['config']->set('gcego-widgets.cache.enabled', true);
        $app['config']->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'models' => [Student::class => 'institution_id', Payment::class => 'institution_id', Result::class => 'institution_id', ActivityLog::class => 'institution_id']]);

        foreach ([Student::class, Result::class, ActivityLog::class, User::class] as $model) {
            Gate::policy($model, Permissive::class);
        }

        Gate::policy(Payment::class, FinancePolicy::class);
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
        Schema::create('students', static function (Table $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('level')->default(1);
            $table->string('gender', 1)->default('m');
            $table->string('national_id')->nullable();
            $table->dateTime('enrolled_at')->nullable();
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });
        Schema::create('payments', static function (Table $table): void {
            $table->id();
            $table->decimal('amount', 12, 2);
            $table->string('method')->default('cash');
            $table->dateTime('paid_at')->nullable();
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });
        Schema::create('results', static function (Table $table): void {
            $table->id();
            $table->boolean('passed')->default(true);
            $table->dateTime('graded_at')->nullable();
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });
        Schema::create('activity_logs', static function (Table $table): void {
            $table->id();
            $table->string('description');
            $table->foreignId('causer_id')->nullable();
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });

        (require dirname(__DIR__).'/vendor/gcego/tables/database/migrations/create_gcego_tables_storage.php')->up();

        $registry = $this->app?->make(Registry::class);
        $registry?->register(Widgets::students());

        foreach (Widgets::all() as $widget) {
            $registry?->register($widget);
        }
    }
}
