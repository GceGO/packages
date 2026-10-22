<?php

declare(strict_types=1);

namespace Gcego\Security\Tests;

use Gcego\Blueprints\BlueprintsServiceProvider;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Data\DataServiceProvider;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Rendering\RenderingServiceProvider;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Security\SecurityServiceProvider;
use Gcego\Security\Tests\Fixtures\InstitutionTenantResolver;
use Gcego\Security\Tests\Fixtures\Models\Student;
use Gcego\Security\Tests\Fixtures\Models\Subject;
use Gcego\Security\Tests\Fixtures\Models\User;
use Gcego\Security\Tests\Fixtures\Policies\SubjectPolicy;
use Illuminate\Database\Schema\Blueprint as Table;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @var array<string, mixed>
     */
    private array $overrides = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    /**
     * @param  array<string, mixed>  $config  Full config keys.
     */
    public function rebuildApplication(array $config = []): Application
    {
        $this->overrides = $config;
        $this->refreshApplication();
        $this->createSchema();

        /** @var Application */
        return $this->app;
    }

    protected function getPackageProviders($app): array
    {
        $app->register(ComponentServiceProvider::class);

        return [LivewireServiceProvider::class, PermissionServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, BlueprintsServiceProvider::class, RuntimeServiceProvider::class, RenderingServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('auth.providers.users', ['driver' => 'eloquent', 'model' => User::class]);
        $app['config']->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('gcego-registry.discovery.enabled', false);
        $app['config']->set('gcego-registry.cache.enabled', false);
        $app['config']->set('gcego-security.tenancy', [
            'enabled' => true,
            'resolver' => InstitutionTenantResolver::class,
            'models' => [Subject::class => 'institution_id', Student::class => 'institution_id'],
        ]);

        foreach ($this->overrides as $key => $value) {
            $app['config']->set($key, $value);
        }

        Gate::policy(Subject::class, SubjectPolicy::class);
    }

    private function createSchema(): void
    {
        Schema::create('users', static function (Table $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->default('');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('institutions', static function (Table $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('memberships', static function (Table $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('institution_id');
            $table->string('role');
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('subjects', static function (Table $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_published')->default(false);
            $table->foreignId('institution_id');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('students', static function (Table $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('national_id');
            $table->foreignId('institution_id');
            $table->timestamps();
        });
        Schema::create('reports', static function (Table $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        $migration = require dirname(__DIR__).'/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub';
        $migration->up();
    }
}
