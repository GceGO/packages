<?php

declare(strict_types=1);

namespace Gcego\Actions\Tests;

use Gcego\Actions\ActionsServiceProvider;
use Gcego\Actions\Tests\Fixtures\Blueprints;
use Gcego\Actions\Tests\Fixtures\Models\Course;
use Gcego\Actions\Tests\Fixtures\Models\Student;
use Gcego\Actions\Tests\Fixtures\Models\Subject;
use Gcego\Actions\Tests\Fixtures\Policies\PermissivePolicy;
use Gcego\Actions\Tests\Fixtures\Policies\SubjectPolicy;
use Gcego\Actions\Tests\Fixtures\TenantResolver;
use Gcego\Chroma\ChromaServiceProvider;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Data\DataServiceProvider;
use Gcego\Luminaries\LuminariesServiceProvider;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Security\SecurityServiceProvider;
use Illuminate\Database\Schema\Blueprint as Table;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @var array<string, mixed>
     */
    private array $overrides = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepare();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function rebuildApplication(array $config = []): Application
    {
        $this->overrides = $config;
        $this->refreshApplication();
        $this->prepare();

        /** @var Application */
        return $this->app;
    }

    protected function getPackageProviders($app): array
    {
        $app->register(ComponentServiceProvider::class);

        return [LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class, ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, LuminariesServiceProvider::class, ActionsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('database.connections.archive', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', Fixtures\Models\User::class);
        $app['config']->set('gcego-registry.discovery.enabled', false);
        $app['config']->set('gcego-registry.cache.enabled', false);
        $app['config']->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'models' => [Subject::class => 'institution_id']]);

        foreach ($this->overrides as $key => $value) {
            $app['config']->set($key, $value);
        }

        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::policy(Student::class, PermissivePolicy::class);
        Gate::policy(Course::class, PermissivePolicy::class);
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
            $table->string('status')->default('active');
            $table->timestamps();
        });

        foreach (['testing', 'archive'] as $connection) {
            Schema::connection($connection)->create('subjects', static function (Table $table): void {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable();
                $table->boolean('is_published')->default(false);
                $table->integer('level')->default(1);
                $table->unsignedInteger('institution_id')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::create('students', static function (Table $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('courses', static function (Table $table): void {
            $table->string('code')->primary();
            $table->string('title');
            $table->timestamps();
        });

        $registry = $this->app?->make(Registry::class);
        $registry?->register(Blueprints::subjects());
        $registry?->register(Blueprints::students());
        $registry?->register(Blueprints::courses());
        $registry?->register(Blueprints::other());
    }
}
