<?php

declare(strict_types=1);

use Gcego\Components\ComponentServiceProvider;
use Gcego\Infolists\InfolistManager;
use Gcego\Infolists\Tests\Fixtures\Models\Category;
use Gcego\Infolists\Tests\Fixtures\Models\Membership;
use Gcego\Infolists\Tests\Fixtures\Models\Subject;
use Gcego\Infolists\Tests\Fixtures\Models\Tag;
use Gcego\Infolists\Tests\Fixtures\Models\Teacher;
use Gcego\Infolists\Tests\Fixtures\Models\User;
use Gcego\Infolists\Tests\TestCase;
use Gcego\Security\SecurityManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// Dataset declarations can construct components before the test application starts.
$componentApp = new Application(dirname(__DIR__));
Facade::setFacadeApplication($componentApp);
$componentApp->register(ComponentServiceProvider::class);

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature');

/**
 * @return array{reader: User, editor: User, south_user: User, categories: array<string, Category>, subject: Subject, south: Subject, teachers: array<string, Teacher>}
 */
function world(): array
{
    $users = [];

    foreach (['reader' => 'Reader', 'editor' => 'Editor'] as $key => $name) {
        $users[$key] = User::query()->create(['name' => $name]);
        Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => 1]);
    }

    $southUser = User::query()->create(['name' => 'Editor']);
    Membership::query()->create(['user_id' => $southUser->id, 'institution_id' => 2]);

    $categories = [
        'science' => Category::query()->create(['name' => 'Science', 'institution_id' => 1]),
        'south' => Category::query()->create(['name' => 'South only', 'institution_id' => 2]),
    ];
    $teachers = ['ada' => Teacher::query()->create(['name' => 'Ada Lovelace']), 'alan' => Teacher::query()->create(['name' => 'Alan Turing'])];

    $subject = new Subject([
        'name' => 'Algebra', 'code' => 'MTH101', 'status' => 'published', 'description' => "## Overview\n\nLinear **equations**.", 'notes' => "Line one\nLine two",
        'credits' => 3, 'fee' => '25000.00', 'is_published' => true, 'priority' => 'high',
        'objectives' => [['title' => 'Solve', 'description' => 'Equations'], ['title' => 'Graph', 'description' => 'Lines']],
        'settings' => ['term' => 'First', 'grading' => ['scale' => '20'], 'api_key' => 'sk_live_SECRET'],
        'languages' => ['English', 'French'], 'website' => 'https://gcego.test/algebra', 'contact' => 'teacher@gcego.test', 'logo' => 'https://cdn.gcego.test/logo.png',
        'starts_on' => '2026-01-10', 'published_at' => '2026-01-05 09:30:00', 'secret_notes' => 'TOP SECRET', 'salary_band' => 'B7', 'internal_code' => 'INT-9', 'api_token' => 'tok_live_123',
        'category_id' => $categories['science']->id,
    ]);
    $subject->institution_id = 1;
    $subject->save();
    $subject->topics()->create(['title' => 'Equations', 'teacher_id' => $teachers['ada']->id, 'position' => 1]);
    $subject->topics()->create(['title' => 'Matrices', 'teacher_id' => $teachers['alan']->id, 'position' => 2]);
    $subject->tags()->attach([Tag::query()->create(['label' => 'Core'])->id, Tag::query()->create(['label' => 'Lab'])->id]);

    $south = new Subject(['name' => 'South Physics', 'code' => 'PHY101', 'category_id' => $categories['south']->id]);
    $south->institution_id = 2;
    $south->save();

    return [...$users, 'south_user' => $southUser, 'categories' => $categories, 'subject' => $subject, 'south' => $south, 'teachers' => $teachers];
}

function actAs(User $user, int $institution = 1): void
{
    test()->actingAs($user);
    app()->forgetScopedInstances();
    app(SecurityManager::class)->enterTenant((string) $institution);
}

function infolists(): InfolistManager
{
    return app(InfolistManager::class);
}

function details(string $definition, string|int $key): string
{
    return (string) preg_replace('/\s+/', ' ', infolists()->render($definition, $key)->toHtml());
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

function statusOf(Closure $callback): ?int
{
    try {
        $callback();
    } catch (HttpExceptionInterface $exception) {
        return $exception->getStatusCode();
    } catch (AuthorizationException $exception) {
        return $exception->status() ?? 403;
    }

    return null;
}
