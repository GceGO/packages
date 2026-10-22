<?php

declare(strict_types=1);

namespace Gcego\Tables\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Gcego\Actions\ActionsServiceProvider;
use Gcego\Blueprints\BlueprintsServiceProvider;
use Gcego\Chroma\ChromaServiceProvider;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Data\DataServiceProvider;
use Gcego\Forms\FormsServiceProvider;
use Gcego\Luminaries\LuminariesServiceProvider;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Rendering\RenderingServiceProvider;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Security\SecurityServiceProvider;
use Gcego\Tables\TablesServiceProvider;
use Gcego\Tables\Tests\Fixtures\Blueprints;
use Gcego\Tables\Tests\Fixtures\Models\Category;
use Gcego\Tables\Tests\Fixtures\Models\Document;
use Gcego\Tables\Tests\Fixtures\Models\Subject;
use Gcego\Tables\Tests\Fixtures\Models\Tag;
use Gcego\Tables\Tests\Fixtures\Policies\Permissive;
use Gcego\Tables\Tests\Fixtures\TenantResolver;
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

        return [LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, BlueprintsServiceProvider::class, RuntimeServiceProvider::class, RenderingServiceProvider::class, ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, ActionsServiceProvider::class, FormsServiceProvider::class, BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class, BladeLucideIconsServiceProvider::class, LuminariesServiceProvider::class, TablesServiceProvider::class];
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
        $app['config']->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'models' => [Subject::class => 'institution_id', Category::class => 'institution_id', Document::class => 'institution_id']]);

        foreach ([Subject::class, Category::class, Tag::class, Document::class] as $model) {
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
            $table->string('code')->nullable();
            $table->string('status')->nullable();
            $table->float('credits')->nullable();
            $table->decimal('fee', 12, 2)->nullable();
            $table->boolean('is_published')->default(false);
            $table->date('starts_on')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->unsignedInteger('completion')->nullable();
            $table->string('website')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->text('secret_notes')->nullable();
            $table->string('salary_band')->nullable();
            $table->string('api_token')->nullable();
            $table->unsignedInteger('institution_id')->default(0);
            $table->softDeletes();
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

        (require dirname(__DIR__).'/database/migrations/create_gcego_tables_storage.php')->up();

        $registry = $this->app?->make(Registry::class);
        $registry?->register(Blueprints::subjects());
        $registry?->register(Blueprints::simple());
        $registry?->register(Blueprints::documents());
    }
}
