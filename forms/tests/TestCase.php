<?php

declare(strict_types=1);

namespace Gcego\Forms\Tests;

use Gcego\Actions\ActionsServiceProvider;
use Gcego\Chroma\ChromaServiceProvider;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Data\DataServiceProvider;
use Gcego\Forms\FormsServiceProvider;
use Gcego\Forms\Tests\Fixtures\Blueprints;
use Gcego\Forms\Tests\Fixtures\Models\Category;
use Gcego\Forms\Tests\Fixtures\Models\Subject;
use Gcego\Forms\Tests\Fixtures\Models\Tag;
use Gcego\Forms\Tests\Fixtures\Policies\Permissive;
use Gcego\Forms\Tests\Fixtures\TenantResolver;
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

        return [LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class, ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, LuminariesServiceProvider::class, ActionsServiceProvider::class, FormsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.timezone', 'UTC');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', Fixtures\Models\User::class);
        $app['config']->set('gcego-registry.discovery.enabled', false);
        $app['config']->set('gcego-registry.cache.enabled', false);
        $app['config']->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'models' => [Subject::class => 'institution_id', Category::class => 'institution_id']]);

        foreach ($this->overrides as $key => $value) {
            $app['config']->set($key, $value);
        }

        foreach ([Subject::class, Category::class, Tag::class] as $model) {
            Gate::policy($model, Permissive::class);
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
        Schema::create('categories', static function (Table $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('institution_id');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('tags', static function (Table $table): void {
            $table->id();
            $table->string('label');
            $table->timestamps();
        });
        Schema::create('subjects', static function (Table $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('kind')->nullable();
            $table->text('track')->nullable();
            $table->integer('level')->nullable();
            $table->float('credits')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('agreed')->default(false);
            $table->date('starts_on')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('lesson_time')->nullable();
            $table->text('description')->nullable();
            $table->text('body')->nullable();
            $table->text('objectives')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->string('syllabus')->nullable();
            $table->string('password')->nullable();
            $table->text('secret_notes')->nullable();
            $table->string('locked_code')->nullable();
            $table->string('internal_flag')->nullable();
            $table->string('origin')->nullable();
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });
        Schema::create('subject_tag', static function (Table $table): void {
            $table->foreignId('subject_id');
            $table->foreignId('tag_id');
        });

        $registry = $this->app?->make(Registry::class);
        $registry?->register(Blueprints::subjects());
        $registry?->register(Blueprints::simple());
    }
}
