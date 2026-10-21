<?php

declare(strict_types=1);

use Gcego\Components\ComponentServiceProvider;
use Gcego\Security\SecurityManager;
use Gcego\Tables\Query\ResolvedTable;
use Gcego\Tables\State\TableState;
use Gcego\Tables\TableManager;
use Gcego\Tables\Tests\Fixtures\Models\Category;
use Gcego\Tables\Tests\Fixtures\Models\Membership;
use Gcego\Tables\Tests\Fixtures\Models\Subject;
use Gcego\Tables\Tests\Fixtures\Models\Tag;
use Gcego\Tables\Tests\Fixtures\Models\User;
use Gcego\Tables\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;

// Dataset declarations can construct components before the test application starts.
$componentApp = new Application(dirname(__DIR__));
Facade::setFacadeApplication($componentApp);
$componentApp->register(ComponentServiceProvider::class);

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature');

/**
 * Users in institution 1 (and one in 2), categories, tags and subjects in both institutions.
 *
 * @return array{reader: User, editor: User, admin: User, south_user: User, categories: array<string, Category>, tags: array<string, Tag>, subjects: array<string, Subject>, south: Subject}
 */
function world(): array
{
    $users = [];

    foreach (['reader' => 'Reader', 'editor' => 'Editor', 'admin' => 'Admin'] as $key => $name) {
        $users[$key] = User::query()->create(['name' => $name]);
        Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => 1]);
    }

    $southUser = User::query()->create(['name' => 'Editor']);
    Membership::query()->create(['user_id' => $southUser->id, 'institution_id' => 2]);

    $categories = [
        'science' => Category::query()->create(['name' => 'Science', 'institution_id' => 1]),
        'arts' => Category::query()->create(['name' => 'Arts', 'institution_id' => 1]),
        'south' => Category::query()->create(['name' => 'South only', 'institution_id' => 2]),
    ];
    $tags = ['core' => Tag::query()->create(['label' => 'Core']), 'lab' => Tag::query()->create(['label' => 'Lab'])];

    $make = static function (array $attributes, int $institution = 1): Subject {
        $subject = new Subject($attributes);
        $subject->institution_id = $institution;
        $subject->save();

        return $subject;
    };

    $subjects = [
        'algebra' => $make(['name' => 'Algebra', 'code' => 'MTH101', 'status' => 'published', 'credits' => 3, 'fee' => '25000.00', 'is_published' => true, 'starts_on' => '2026-01-10', 'published_at' => '2026-01-05 09:30:00', 'completion' => 40, 'website' => 'https://gcego.test/algebra', 'category_id' => $categories['science']->id, 'secret_notes' => 'TOP SECRET', 'salary_band' => 'B7', 'api_token' => 'tok_live_123']),
        'biology' => $make(['name' => 'Biology', 'code' => 'BIO101', 'status' => 'draft', 'credits' => 4, 'fee' => '60000.00', 'is_published' => false, 'starts_on' => '2026-02-15', 'completion' => 100, 'website' => 'javascript:alert(1)', 'category_id' => $categories['science']->id]),
        'chemistry' => $make(['name' => 'Chemistry', 'code' => 'CHM101', 'status' => 'published', 'credits' => 2.5, 'fee' => '50000.00', 'is_published' => true, 'starts_on' => '2026-03-01', 'published_at' => '2026-03-01 23:30:00', 'category_id' => $categories['arts']->id]),
        'drama' => $make(['name' => 'Drama', 'code' => 'DRA101', 'status' => 'archived', 'credits' => null, 'is_published' => false, 'category_id' => $categories['arts']->id]),
    ];
    $subjects['algebra']->tags()->attach([$tags['core']->id, $tags['lab']->id]);

    $south = $make(['name' => 'South Physics', 'code' => 'PHY101', 'status' => 'published', 'category_id' => $categories['south']->id], 2);

    return [...$users, 'south_user' => $southUser, 'categories' => $categories, 'tags' => $tags, 'subjects' => $subjects, 'south' => $south];
}

function actAs(User $user, int $institution = 1): void
{
    test()->actingAs($user);
    app()->forgetScopedInstances();
    app(SecurityManager::class)->enterTenant((string) $institution);
}

function tables(): TableManager
{
    return app(TableManager::class);
}

function resolved(string $definition = 'subjects'): ResolvedTable
{
    return tables()->resolve($definition);
}

/**
 * @param  array<string, mixed>  $state
 */
function state(array $state = [], string $definition = 'subjects'): TableState
{
    $table = resolved($definition);

    return tables()->normalizer($table->schema, tables()->access($table->schema), model: tables()->model($table))->normalize($state);
}

/**
 * Names of the rows for a state, in order.
 *
 * @param  array<string, mixed>  $state
 * @return list<string>
 */
function names(array $state = [], string $definition = 'subjects'): array
{
    $table = resolved($definition);
    $access = tables()->access($table->schema);
    $result = tables()->query($table, $access, tables()->normalizer($table->schema, $access, model: tables()->model($table))->normalize($state));

    return array_values(array_map(static fn ($record): string => $record->name ?? $record->title, $result->items()));
}

/**
 * @return list<string>
 */
function queriesDuring(Closure $callback): array
{
    $queries = [];
    $recording = true;
    Event::listen(QueryExecuted::class, static function (QueryExecuted $query) use (&$queries, &$recording): void {
        if ($recording) {
            $queries[] = $query->sql;
        }
    });

    try {
        $callback();
    } finally {
        $recording = false;
    }

    return $queries;
}
