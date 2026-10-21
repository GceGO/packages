<?php

declare(strict_types=1);

use Gcego\Components\ComponentServiceProvider;
use Gcego\Testing\Integrations\PestIntegration;
use Gcego\Testing\Tests\Fixtures\Models\Membership;
use Gcego\Testing\Tests\Fixtures\Models\Subject;
use Gcego\Testing\Tests\Fixtures\Models\User;
use Gcego\Testing\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\AssertionFailedError;

// Dataset declarations can construct components before the test application starts.
$componentApp = new Application(dirname(__DIR__));
Facade::setFacadeApplication($componentApp);
$componentApp->register(ComponentServiceProvider::class);

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature', 'Security');

PestIntegration::register();

/**
 * Institution 1: Admin (administrator), Editor (teacher), Student (student);
 * subjects Algebra (published, with a secret note) and Draft (unpublished).
 * Institution 2: South (administrator); subject Southern.
 *
 * @return array{admin: User, editor: User, student: User, south: User, algebra: Subject, draft: Subject, southern: Subject}
 */
function world(): array
{
    $users = [];

    foreach (['admin' => ['Admin', 1, 'administrator'], 'editor' => ['Editor', 1, 'teacher'], 'student' => ['Student', 1, 'student'], 'south' => ['South', 2, 'administrator']] as $key => [$name, $institution, $role]) {
        $users[$key] = User::query()->create(['name' => $name, 'email' => strtolower($name).'@example.test']);
        Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => $institution, 'role' => $role]);
    }

    return [
        ...$users,
        'algebra' => Subject::query()->create(['institution_id' => 1, 'name' => 'Algebra', 'code' => 'ALG', 'secret_note' => 'algebra-secret', 'published' => true]),
        'draft' => Subject::query()->create(['institution_id' => 1, 'name' => 'Draft', 'code' => 'DRF', 'published' => false]),
        'southern' => Subject::query()->create(['institution_id' => 2, 'name' => 'Southern', 'code' => 'STH', 'secret_note' => 'south-secret', 'published' => true]),
    ];
}

/**
 * Runs an assertion that must fail and returns its failure message.
 */
function failure(Closure $assertion): string
{
    try {
        $assertion();
    } catch (AssertionFailedError $failure) {
        return $failure->getMessage();
    }

    throw new RuntimeException('The assertion passed, but it was expected to fail.');
}
