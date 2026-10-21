<?php

declare(strict_types=1);

namespace Gcego\Infolists\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Gcego\Actions\ActionsServiceProvider;
use Gcego\Chroma\ChromaServiceProvider;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Data\DataServiceProvider;
use Gcego\Forms\FormsServiceProvider;
use Gcego\Infolists\InfolistsServiceProvider;
use Gcego\Infolists\Tests\Fixtures\Blueprints;
use Gcego\Infolists\Tests\Fixtures\Models\Category;
use Gcego\Infolists\Tests\Fixtures\Models\Course;
use Gcego\Infolists\Tests\Fixtures\Models\Document;
use Gcego\Infolists\Tests\Fixtures\Models\Report;
use Gcego\Infolists\Tests\Fixtures\Models\Subject;
use Gcego\Infolists\Tests\Fixtures\Models\Tag;
use Gcego\Infolists\Tests\Fixtures\Models\Teacher;
use Gcego\Infolists\Tests\Fixtures\Models\Topic;
use Gcego\Infolists\Tests\Fixtures\Policies\Permissive;
use Gcego\Infolists\Tests\Fixtures\TenantResolver;
use Gcego\Luminaries\LuminariesServiceProvider;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Security\SecurityServiceProvider;
use Gcego\Tables\TablesServiceProvider;
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
        $app->register(ComponentServiceProvider::class);

        return [LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class, ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, ActionsServiceProvider::class, FormsServiceProvider::class, BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class, BladeLucideIconsServiceProvider::class, LuminariesServiceProvider::class, TablesServiceProvider::class, InfolistsServiceProvider::class];
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
        $app['config']->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'models' => [Subject::class => 'institution_id', Category::class => 'institution_id', Document::class => 'institution_id', Report::class => 'institution_id']]);

        foreach ([Subject::class, Category::class, Tag::class, Topic::class, Teacher::class, Document::class, Report::class, Course::class] as $model) {
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
        Schema::create('teachers', static function (Table $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('subjects', static function (Table $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->float('credits')->nullable();
            $table->decimal('fee', 12, 2)->nullable();
            $table->boolean('is_published')->default(false);
            $table->string('priority')->nullable();
            $table->text('objectives')->nullable();
            $table->text('settings')->nullable();
            $table->text('languages')->nullable();
            $table->string('website')->nullable();
            $table->string('contact')->nullable();
            $table->string('logo')->nullable();
            $table->date('starts_on')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->text('secret_notes')->nullable();
            $table->string('salary_band')->nullable();
            $table->string('internal_code')->nullable();
            $table->string('archive_reason')->nullable();
            $table->string('api_token')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->unsignedInteger('institution_id')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('topics', static function (Table $table): void {
            $table->id();
            $table->foreignId('subject_id');
            $table->foreignId('teacher_id')->nullable();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::create('subject_tag', static function (Table $table): void {
            $table->foreignId('subject_id');
            $table->foreignId('tag_id');
        });
        Schema::create('documents', static function (Table $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });
        Schema::create('reports', static function (Table $table): void {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->unsignedInteger('institution_id')->default(0);
            $table->timestamps();
        });
        Schema::create('courses', static function (Table $table): void {
            $table->string('course_code')->primary();
            $table->string('title');
            $table->timestamps();
        });

        $registry = $this->app?->make(Registry::class);

        foreach (Blueprints::all() as $blueprint) {
            $registry?->register($blueprint);
        }
    }
}
