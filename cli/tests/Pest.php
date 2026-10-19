<?php

declare(strict_types=1);

use Gcego\Cli\Tests\Fixtures\Models\Membership;
use Gcego\Cli\Tests\Fixtures\Models\Subject;
use Gcego\Cli\Tests\Fixtures\Models\User;
use Gcego\Cli\Tests\TestCase;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Security\SecurityConfig;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature', 'Security');

function ws(string $path = ''): string
{
    return TestCase::workspace($path);
}

/**
 * The definition a generated file returns, and its kind's validation errors.
 *
 * @return array{0: object, 1: list<string>}
 */
function loadDefinition(string $relative, ?string $kind = null): array
{
    $definition = (static fn (string $file): mixed => require $file)(ws($relative));
    assert(is_object($definition));
    $errors = $kind === null ? [] : (app(DefinitionKinds::class)->get($kind)?->validate($definition) ?? ['unknown kind '.$kind]);

    return [$definition, $errors];
}

/**
 * Institution 1: Admin, Editor (both may enter the admin panel), Reader; subjects Chemistry, Physics.
 * Institution 2: South; subject Biology.
 *
 * @return array{admin: User, editor: User, reader: User, south: User, chemistry: Subject, physics: Subject, biology: Subject}
 */
function world(): array
{
    $users = [];

    foreach (['admin' => ['Admin', 1], 'editor' => ['Editor', 1], 'reader' => ['Reader', 1], 'south' => ['South', 2]] as $key => [$name, $institution]) {
        $users[$key] = User::query()->create(['name' => $name, 'email' => strtolower($name).'@example.test']);
        Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => $institution]);
    }

    $subjects = [];

    foreach (['chemistry' => ['Chemistry', 'CHE', 1], 'physics' => ['Physics', 'PHY', 1], 'biology' => ['Biology', 'BIO', 2]] as $key => [$name, $code, $institution]) {
        $subject = new Subject(['name' => $name, 'code' => $code]);
        $subject->forceFill(['institution_id' => $institution])->save();
        $subjects[$key] = $subject;
    }

    return [...$users, ...$subjects];
}

/**
 * @return array<string, string>
 */
function tenantSession(int $institution = 1): array
{
    return [app(SecurityConfig::class)->tenantSessionKey => (string) $institution];
}

function asMember(User $user, int $institution = 1): TestCase
{
    app()->forgetScopedInstances();

    return test()->actingAs($user)->withSession(tenantSession($institution));
}

/**
 * Runs an artisan command; returns [exit code, output].
 *
 * @param  array<string, mixed>  $parameters
 * @return array{0: int, 1: string}
 */
function gcego(string $command, array $parameters = []): array
{
    $output = new BufferedOutput;
    $code = Artisan::call($command, [...$parameters, '--no-interaction' => true], $output);
    $text = $output->fetch();

    // Console output wraps long lines: compare text, not layout (JSON is never wrapped).
    return [$code, ($parameters['--format'] ?? null) === 'json' ? $text : (string) preg_replace('/[ \t]*\n[ \t]*(?=\S)/', ' ', $text)];
}

/**
 * @param  array<string, mixed>  $parameters
 * @return array<string, mixed>
 */
function gcegoJson(string $command, array $parameters = []): array
{
    [, $output] = gcego($command, [...$parameters, '--format' => 'json']);
    $decoded = json_decode($output, true);
    expect($decoded)->toBeArray("Not JSON: {$output}");

    return $decoded;
}
