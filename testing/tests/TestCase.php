<?php

declare(strict_types=1);

namespace Gcego\Testing\Tests;

use Gcego\Testing\Environment\PackageTestCase;
use Gcego\Testing\Tests\Fixtures\Models\Subject;
use Gcego\Testing\Tests\Fixtures\Models\User;
use Gcego\Testing\Tests\Fixtures\Policies\SubjectPolicy;
use Gcego\Testing\Tests\Fixtures\Providers\DefinitionsProvider;
use Gcego\Testing\Tests\Fixtures\TenantFixtures;
use Gcego\Testing\Tests\Fixtures\TenantResolver;
use Illuminate\Database\Schema\Blueprint as Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * The toolkit tests itself with its own PackageTestCase: every installed
 * GCEGO provider and TestApplicationFactory's isolated configuration.
 */
abstract class TestCase extends PackageTestCase
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

    protected function gcegoConfiguration(): array
    {
        $fixtures = __DIR__.'/Fixtures';

        return [
            'auth.providers.users.model' => User::class,
            'gcego-registry.discovery.enabled' => true,
            'gcego-registry.discovery.roots' => [],
            'gcego-registry.providers' => [DefinitionsProvider::class],
            'gcego-modules.discovery.paths' => [$fixtures.'/Modules'],
            'gcego-panels.discovery.paths' => [$fixtures.'/Panels'],
            'gcego-workflows.discovery.paths' => [],
            'gcego-security.tenancy' => ['enabled' => true, 'resolver' => TenantResolver::class, 'parameter' => 'institution', 'models' => [Subject::class => 'institution_id']],
            'gcego-testing.fixtures.tenants' => TenantFixtures::class,
            ...self::$overrides,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::define('access-admin', static fn (User $user): bool => $user->name === 'Admin');
        Gate::define('view-reports', static fn (User $user): bool => $user->name === 'Admin');
        Gate::define('gcego-rendering.render', static fn (?User $user): bool => true);
    }

    protected function defineRoutes($router): void
    {
        Route::get('/login', static fn (): string => 'Sign in')->name('login');
        Route::get('/home', static fn (): string => 'Home')->name('home');
        Route::get('/reports', static fn (): string => 'Reports')->name('reports');
        Route::post('/logout', static fn (): string => 'Signed out')->name('logout');
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
            $table->string('role');
            $table->timestamps();
        });
        Schema::create('subjects', static function (Table $table): void {
            $table->id();
            $table->unsignedInteger('institution_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('secret_note')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
        });

        $packages = dirname(__DIR__).'/vendor/gcego';

        foreach ([...(glob($packages.'/notifications/database/migrations/*.php') ?: []), ...(glob($packages.'/media/database/migrations/*.php') ?: []), ...(glob($packages.'/workflows/database/migrations/*.php') ?: [])] as $migration) {
            (require $migration)->up();
        }
    }
}
