<?php

declare(strict_types=1);

namespace Gcego\Cli\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Gcego\Actions\ActionsServiceProvider;
use Gcego\Analytics\AnalyticsServiceProvider;
use Gcego\Blueprints\BlueprintsServiceProvider;
use Gcego\Chroma\ChromaServiceProvider;
use Gcego\Cli\CliServiceProvider;
use Gcego\Cli\Tests\Fixtures\Models\Subject;
use Gcego\Cli\Tests\Fixtures\Models\User;
use Gcego\Cli\Tests\Fixtures\Policies\SubjectPolicy;
use Gcego\Cli\Tests\Fixtures\TenantResolver;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Data\DataServiceProvider;
use Gcego\Forms\FormsServiceProvider;
use Gcego\Infolists\InfolistsServiceProvider;
use Gcego\Layouts\LayoutsServiceProvider;
use Gcego\Luminaries\LuminariesServiceProvider;
use Gcego\Media\MediaServiceProvider;
use Gcego\Modules\ModulesServiceProvider;
use Gcego\Navigation\NavigationServiceProvider;
use Gcego\Notifications\NotificationsServiceProvider;
use Gcego\Pages\PagesServiceProvider;
use Gcego\Panels\PanelsServiceProvider;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Rendering\RenderingServiceProvider;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Security\SecurityServiceProvider;
use Gcego\Settings\SettingsServiceProvider;
use Gcego\Studio\StudioServiceProvider;
use Gcego\Tables\TablesServiceProvider;
use Gcego\Testing\TestingServiceProvider;
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

/**
 * Every test runs against an empty application workspace (composer.json
 * with App\ and Tests\ PSR-4 rules, app/GCEGo discovery folders), so
 * generated files are discovered, validated and run like in an application.
 */
abstract class TestCase extends Orchestra
{
    /** @var array<string, mixed> */
    public static array $overrides = [];

    private static bool $autoloader = false;

    protected function setUp(): void
    {
        self::workspaceSkeleton();
        parent::setUp();
        $this->prepare();
    }

    protected function tearDown(): void
    {
        self::$overrides = [];
        parent::tearDown();
        (new Filesystem)->deleteDirectory(self::workspace());
    }

    public static function workspace(string $path = ''): string
    {
        return sys_get_temp_dir().'/gcego-cli-tests-'.getmypid().($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    /**
     * A new application over the same workspace (after generating files), with $config set.
     *
     * @param  array<string, mixed>  $config
     */
    public function reboot(array $config = []): void
    {
        self::$overrides = $config;
        $this->refreshApplication();
        $this->prepare();
    }

    protected function getPackageProviders($app): array
    {
        $app->register(ComponentServiceProvider::class);

        return [
            LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, BlueprintsServiceProvider::class, RuntimeServiceProvider::class, RenderingServiceProvider::class,
            ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, ActionsServiceProvider::class,
            FormsServiceProvider::class, BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class, BladeLucideIconsServiceProvider::class, LuminariesServiceProvider::class, TablesServiceProvider::class, InfolistsServiceProvider::class, WidgetsServiceProvider::class,
            LayoutsServiceProvider::class, NavigationServiceProvider::class, PagesServiceProvider::class, ModulesServiceProvider::class,
            PanelsServiceProvider::class, NotificationsServiceProvider::class, MediaServiceProvider::class, WorkflowsServiceProvider::class,
            SettingsServiceProvider::class, AnalyticsServiceProvider::class, TestingServiceProvider::class, StudioServiceProvider::class,
            CliServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $original = $app->basePath();
        $app->setBasePath(self::workspace());
        $app->useStoragePath($original.'/storage');
        $app->useBootstrapPath($original.'/bootstrap');
        $app->useDatabasePath(self::workspace('database'));

        $config = $app['config'];
        $ws = self::workspace('app/GCEGo');
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('queue.default', 'sync');
        $config->set('auth.providers.users.model', User::class);
        $config->set('gcego-registry.cache.enabled', false);
        $config->set('gcego-registry.discovery.roots', [
            ['path' => $ws.'/Blueprints', 'kind' => 'blueprint', 'entrypoint' => 'directory'],
            ['path' => $ws.'/Pages', 'kind' => 'page', 'entrypoint' => 'file'],
            ['path' => $ws.'/Widgets', 'kind' => 'widget', 'entrypoint' => 'file'],
            ['path' => $ws.'/Layouts', 'kind' => 'layout', 'entrypoint' => 'file'],
            ['path' => $ws.'/Navigation', 'kind' => 'navigation', 'entrypoint' => 'file'],
        ]);
        $config->set('gcego-modules.discovery.paths', [$ws.'/Modules']);
        $config->set('gcego-panels.discovery.paths', [$ws.'/Panels']);
        $config->set('gcego-workflows.discovery.paths', [$ws.'/Workflows']);
        $config->set('gcego-settings.discovery.paths', [$ws.'/Settings']);
        $config->set('gcego-settings.discovery.pages', [$ws.'/SettingsPages']);
        $config->set('gcego-analytics.discovery.paths', [$ws.'/Analytics']);
        $config->set('gcego-cli.paths', ['support' => $ws.'/Support', 'notification' => self::workspace('app/Notifications'), 'test' => self::workspace('tests/Feature')]);
        $config->set('gcego-cli.stubs.custom_path', self::workspace('stubs/gcego'));
        $config->set('gcego-studio.assets.css', self::workspace('resources/css/app.css'));
        $config->set('gcego-studio.assets.js', self::workspace('resources/js/app.js'));
        $config->set('gcego-studio.assets.manifest', self::workspace('public/build/manifest.json'));
        $config->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'parameter' => 'institution', 'models' => [Subject::class => 'institution_id']]);

        foreach (self::$overrides as $key => $value) {
            $config->set($key, $value);
        }

        if (is_string(self::$overrides['app.env'] ?? null)) {
            $app['env'] = self::$overrides['app.env'];
        }

        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::define('access-admin', static fn (User $user): bool => in_array($user->name, ['Admin', 'Editor'], true));
    }

    protected function defineRoutes($router): void
    {
        Route::get('/login', static fn (): string => 'Sign in')->name('login');
    }

    /**
     * The workspace's composer.json and an autoloader for its App\ and Tests\ classes.
     */
    private static function workspaceSkeleton(): void
    {
        @mkdir(self::workspace('app'), 0777, true);
        file_put_contents(self::workspace('composer.json'), (string) json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']], 'autoload-dev' => ['psr-4' => ['Tests\\' => 'tests/']]]));

        if (! self::$autoloader) {
            self::$autoloader = true;
            spl_autoload_register(static function (string $class): void {
                foreach (['App\\' => 'app/', 'Tests\\' => 'tests/'] as $prefix => $directory) {
                    if (str_starts_with($class, $prefix) && is_file($file = self::workspace($directory.str_replace('\\', '/', substr($class, strlen($prefix))).'.php'))) {
                        require $file;
                    }
                }
            });
        }
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
            $table->string('name', 120);
            $table->string('code', 12);
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->date('published_on')->nullable();
            $table->integer('credits')->nullable();
            $table->string('status')->default('draft');
            $table->string('password')->nullable();
            $table->string('api_token')->nullable();
            $table->text('internal_notes')->nullable();
            $table->unsignedInteger('institution_id');
            $table->timestamps();
        });

        foreach (glob(dirname(__DIR__).'/vendor/gcego/*/database/migrations/*.php') ?: [] as $migration) {
            (require $migration)->up();
        }
    }
}
