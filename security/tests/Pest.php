<?php

declare(strict_types=1);

use Gcego\Components\ComponentServiceProvider;
use Gcego\Security\SecurityManager;
use Gcego\Security\Tests\Fixtures\Models\Institution;
use Gcego\Security\Tests\Fixtures\Models\Membership;
use Gcego\Security\Tests\Fixtures\Models\Student;
use Gcego\Security\Tests\Fixtures\Models\Subject;
use Gcego\Security\Tests\Fixtures\Models\User;
use Gcego\Security\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

// Dataset declarations can construct components before the test application starts.
$componentApp = new Application(dirname(__DIR__));
Facade::setFacadeApplication($componentApp);
$componentApp->register(ComponentServiceProvider::class);

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature');

/**
 * Two institutions; a teacher of North only, an administrator of both, a
 * platform user with no membership; subjects and students in each.
 *
 * @return array{north: Institution, south: Institution, closed: Institution, teacher: User, admin: User, outsider: User, assistant: User, subjects: array<string, Subject>, students: array<string, Student>}
 */
function world(): array
{
    $north = Institution::query()->create(['name' => 'North High']);
    $south = Institution::query()->create(['name' => 'South High']);
    $closed = Institution::query()->create(['name' => 'Closed High', 'active' => false]);
    $teacher = User::query()->create(['name' => 'Teacher', 'email_verified_at' => now()]);
    $admin = User::query()->create(['name' => 'Admin', 'email_verified_at' => now()]);
    $outsider = User::query()->create(['name' => 'Outsider', 'email_verified_at' => now()]);
    $assistant = User::query()->create(['name' => 'Assistant', 'email_verified_at' => now()]);

    Membership::query()->create(['user_id' => $teacher->id, 'institution_id' => $north->id, 'role' => 'teacher']);
    Membership::query()->create(['user_id' => $admin->id, 'institution_id' => $north->id, 'role' => 'administrator']);
    Membership::query()->create(['user_id' => $admin->id, 'institution_id' => $south->id, 'role' => 'teacher']);
    Membership::query()->create(['user_id' => $assistant->id, 'institution_id' => $north->id, 'role' => 'assistant']);
    Membership::query()->create(['user_id' => $teacher->id, 'institution_id' => $closed->id, 'role' => 'administrator']);
    Membership::query()->create(['user_id' => $outsider->id, 'institution_id' => $north->id, 'role' => 'teacher', 'status' => 'ended']);

    $subjects = [
        'algebra' => Subject::query()->create(['name' => 'Algebra', 'is_published' => true, 'institution_id' => $north->id]),
        'draft' => Subject::query()->create(['name' => 'Draft Physics', 'is_published' => false, 'institution_id' => $north->id]),
        'south' => Subject::query()->create(['name' => 'South Biology', 'is_published' => true, 'institution_id' => $south->id]),
        'trashed' => Subject::query()->create(['name' => 'Trashed', 'is_published' => true, 'institution_id' => $north->id]),
    ];
    $subjects['trashed']->delete();

    $students = [
        'north' => Student::query()->create(['name' => 'Ada', 'national_id' => 'NAT-NORTH-1', 'institution_id' => $north->id]),
        'south' => Student::query()->create(['name' => 'Bo', 'national_id' => 'NAT-SOUTH-1', 'institution_id' => $south->id]),
    ];

    return compact('north', 'south', 'closed', 'teacher', 'admin', 'outsider', 'assistant', 'subjects', 'students');
}

function security(): SecurityManager
{
    return app(SecurityManager::class);
}

/**
 * Simulate the start of a new request: Laravel flushes scoped instances (tenant, context, caches).
 */
function newRequest(): void
{
    app()->forgetScopedInstances();
}
