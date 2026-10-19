<?php

declare(strict_types=1);

namespace Gcego\Analytics\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Gcego\Actions\ActionsServiceProvider;
use Gcego\Analytics\AnalyticsServiceProvider;
use Gcego\Analytics\Tests\Fixtures\Models\AssessmentResult;
use Gcego\Analytics\Tests\Fixtures\Models\Subject;
use Gcego\Analytics\Tests\Fixtures\Models\User;
use Gcego\Analytics\Tests\Fixtures\Policies\RecordPolicy;
use Gcego\Analytics\Tests\Fixtures\TenantResolver;
use Gcego\Chroma\ChromaServiceProvider;
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
use Gcego\Tables\TablesServiceProvider;
use Gcego\Widgets\WidgetsServiceProvider;
use Gcego\Workflows\WorkflowsServiceProvider;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepare();
    }

    protected function tearDown(): void
    {
        self::$overrides = [];
        (new Filesystem)->deleteDirectory(self::storage());

        parent::tearDown();
    }

    public static function storage(): string
    {
        return sys_get_temp_dir().'/gcego-analytics-tests-'.getmypid();
    }

    /**
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
        return [LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class, ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, ActionsServiceProvider::class, FormsServiceProvider::class, BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class, BladeLucideIconsServiceProvider::class, LuminariesServiceProvider::class, TablesServiceProvider::class, InfolistsServiceProvider::class, WidgetsServiceProvider::class, LayoutsServiceProvider::class, NavigationServiceProvider::class, PagesServiceProvider::class, ModulesServiceProvider::class, PanelsServiceProvider::class, NotificationsServiceProvider::class, WorkflowsServiceProvider::class, AnalyticsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $config->set('app.url', 'http://localhost');
        $config->set('app.timezone', 'UTC');
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('queue.default', 'sync');
        $config->set('mail.default', 'array');
        $config->set('filesystems.disks.local', ['driver' => 'local', 'root' => self::storage(), 'throw' => false]);
        $config->set('auth.providers.users.model', User::class);
        $config->set('gcego-registry.discovery.enabled', true);
        $config->set('gcego-registry.discovery.roots', [['path' => __DIR__.'/Fixtures/Pages', 'kind' => 'page', 'entrypoint' => 'file']]);
        $config->set('gcego-registry.cache.enabled', false);
        $config->set('gcego-modules.discovery.paths', [__DIR__.'/Fixtures/Modules']);
        $config->set('gcego-panels.discovery.paths', [__DIR__.'/Fixtures/Panels']);
        $config->set('gcego-workflows.discovery.paths', []);
        $config->set('gcego-analytics.discovery.paths', [__DIR__.'/Fixtures/Analytics']);
        $config->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'parameter' => 'institution', 'models' => [Subject::class => 'institution_id', AssessmentResult::class => 'institution_id']]);

        foreach (self::$overrides as $key => $value) {
            $config->set($key, $value);
        }

        Gate::policy(Subject::class, RecordPolicy::class);
        Gate::policy(AssessmentResult::class, RecordPolicy::class);
        Gate::define('use-desk', static fn (User $user): bool => true);
        Gate::define('analytics.view', static fn (User $user): bool => $user->name !== 'Visitor');
        Gate::define('reports.academic', static fn (User $user): bool => in_array($user->name, ['Admin', 'Teacher', 'South'], true));
        Gate::define('reports.levels', static fn (User $user): bool => $user->name === 'Admin');
        Gate::define('students.contact', static fn (User $user): bool => $user->name === 'Admin');
        Gate::define('analytics.export', static fn (User $user): bool => in_array($user->name, ['Admin', 'Teacher'], true));
        Gate::define('analytics.cross-institution', static fn (User $user): bool => false);
        Gate::define('gcego-workflows.manage', static fn (User $user): bool => $user->name === 'Admin');
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
            $table->timestamps();
        });
        Schema::create('subjects', static function (Table $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('institution_id');
            $table->timestamps();
        });
        Schema::create('assessment_results', static function (Table $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('subject_id')->nullable();
            $table->unsignedInteger('institution_id');
            $table->string('level', 1);
            $table->string('kind');
            $table->string('status');
            $table->float('score')->nullable();
            $table->unsignedInteger('time_spent')->default(0);
            $table->string('email')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        foreach ([...(glob(dirname(__DIR__, 2).'/notifications/database/migrations/*.php') ?: []), ...(glob(dirname(__DIR__, 2).'/workflows/database/migrations/*.php') ?: []), ...(glob(dirname(__DIR__).'/database/migrations/*.php') ?: [])] as $migration) {
            (require $migration)->up();
        }
    }
}
