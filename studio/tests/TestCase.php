<?php

declare(strict_types=1);

namespace Gcego\Studio\Tests;

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
use Gcego\Notifications\NotificationsServiceProvider;
use Gcego\Pages\PagesServiceProvider;
use Gcego\Panels\PanelsServiceProvider;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Security\SecurityServiceProvider;
use Gcego\Sentinel\SentinelServiceProvider;
use Gcego\Studio\StudioServiceProvider;
use Gcego\Studio\Tests\Fixtures\Models\Subject;
use Gcego\Studio\Tests\Fixtures\Models\User;
use Gcego\Studio\Tests\Fixtures\Policies\SubjectPolicy;
use Gcego\Studio\Tests\Fixtures\TenantResolver;
use Gcego\Tables\TablesServiceProvider;
use Gcego\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint as Table;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use MallardDuck\LucideIcons\BladeLucideIconsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @var array<string, mixed>
     */
    public static array $overrides = [];

    /** Providers to leave out of the next application (missing-package tests). */
    public static array $without = [];

    /** Whether the next application signs in through gcego/sentinel. */
    public static bool $sentinel = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepare();
    }

    protected function tearDown(): void
    {
        self::$overrides = [];
        self::$without = [];
        self::$sentinel = false;
        (new Filesystem)->deleteDirectory(self::workspace());

        parent::tearDown();
    }

    /**
     * A per-process scratch directory for installer targets.
     */
    public static function workspace(string $path = ''): string
    {
        return sys_get_temp_dir().'/gcego-studio-tests-'.getmypid().($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<class-string>  $without
     */
    public function reboot(array $config, array $without = []): void
    {
        self::$overrides = $config;
        self::$without = $without;
        $this->refreshApplication();
        $this->prepare();
    }

    protected function getPackageProviders($app): array
    {
        $app->register(ComponentServiceProvider::class);
        $authentication = self::$sentinel ? [SentinelServiceProvider::class] : [];

        return array_values(array_diff([LivewireServiceProvider::class, ...$authentication, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class, ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, ActionsServiceProvider::class, FormsServiceProvider::class, BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class, BladeLucideIconsServiceProvider::class, LuminariesServiceProvider::class, TablesServiceProvider::class, InfolistsServiceProvider::class, WidgetsServiceProvider::class, LayoutsServiceProvider::class, NavigationServiceProvider::class, PagesServiceProvider::class, ModulesServiceProvider::class, PanelsServiceProvider::class, NotificationsServiceProvider::class, StudioServiceProvider::class], self::$without));
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        if (is_file($app->getCachedConfigPath())) {
            // As Laravel's LoadConfiguration does with bootstrap/cache/config.php (Testbench does not).
            $config->set(require $app->getCachedConfigPath());
            $this->defineAuthorization();

            return;
        }

        $config->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $config->set('app.url', 'http://localhost');
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('queue.default', 'sync');
        $config->set('auth.providers.users.model', User::class);
        $config->set('gcego-registry.discovery.enabled', true);
        $config->set('gcego-registry.discovery.roots', [['path' => __DIR__.'/Fixtures/Pages', 'kind' => 'page', 'entrypoint' => 'file']]);
        $config->set('gcego-registry.cache.enabled', false);
        $config->set('gcego-modules.discovery.paths', [__DIR__.'/Fixtures/Modules']);
        $config->set('gcego-panels.discovery.paths', [__DIR__.'/Fixtures/Panels']);
        $config->set('gcego-studio.assets.css', self::workspace('resources/css/app.css'));
        $config->set('gcego-studio.assets.js', self::workspace('resources/js/app.js'));
        $config->set('gcego-studio.assets.manifest', self::workspace('public/build/manifest.json'));
        $config->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'parameter' => 'institution', 'models' => [Subject::class => 'institution_id']]);

        if (self::$sentinel) {
            $config->set('hashing.bcrypt.rounds', 4);
            $config->set('gcego-sentinel.home', '/admin');
            $config->set('gcego-sentinel.features', ['password_reset' => false, 'email_verification' => false, 'two_factor' => false, 'profile' => false, 'password_update' => false]);
            $config->set('gcego-sentinel.account_states.attribute', 'status');
        }

        foreach (self::$overrides as $key => $value) {
            $config->set($key, $value);
        }

        if (is_string(self::$overrides['app.env'] ?? null)) {
            $app['env'] = self::$overrides['app.env'];
        }

        $this->defineAuthorization();
    }

    private function defineAuthorization(): void
    {
        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::define('access-admin', static fn (User $user): bool => in_array($user->name, ['Admin', 'Editor'], true));
        Gate::define('view-reports', static fn (User $user): bool => $user->name === 'Admin');
    }

    protected function defineRoutes($router): void
    {
        if (self::$sentinel) {
            return;   // gcego/sentinel registers login and logout
        }

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
            $table->string('status')->default('active');
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
            $table->string('code');
            $table->unsignedInteger('institution_id');
            $table->timestamps();
        });

        foreach ([...glob(dirname(__DIR__).'/vendor/gcego/tables/database/migrations/*.php') ?: [], ...glob(dirname(__DIR__).'/vendor/gcego/notifications/database/migrations/*.php') ?: []] as $migration) {
            (require $migration)->up();
        }
    }
}
