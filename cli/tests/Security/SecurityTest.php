<?php

declare(strict_types=1);

use Gcego\Cli\ExitCode;
use Gcego\Cli\Tests\Fixtures\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * @return list<string>
 */
function generatedFiles(): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ws('app'), FilesystemIterator::SKIP_DOTS)) as $file) {
        $files[] = (string) $file;
    }

    sort($files);

    return $files;
}

it('rejects names, options and identifiers that could escape or inject code', function (string $command, array $parameters, int $exit): void {
    gcego('gcego:make-module', ['name' => 'Academics']);
    $before = generatedFiles();
    [$code] = gcego($command, $parameters);

    expect($code)->toBe($exit)->and(generatedFiles())->toBe($before);
})->with([
    'traversal in a name' => ['gcego:make-page', ['name' => '../../config/app'], ExitCode::InvalidInput],
    'traversal in a module' => ['gcego:make-resource', ['name' => 'Subject', '--module' => '../Academics', '--model' => Subject::class], ExitCode::InvalidInput],
    'php in a model option' => ['gcego:make-resource', ['name' => 'Subject', '--module' => 'academics', '--model' => 'Subject::class); system("id"); //'], ExitCode::InvalidInput],
    'a class that is not a model' => ['gcego:make-resource', ['name' => 'Subject', '--module' => 'academics', '--model' => stdClass::class], ExitCode::MissingDependency],
    'php in an ability' => ['gcego:make-page', ['name' => 'Reports', '--ability' => "x'); system('id'); ('"], ExitCode::InvalidInput],
    'php in a navigation item' => ['gcego:make-navigation', ['name' => 'Menu', '--item' => ["Home='), system('id'), ('=/"]], ExitCode::InvalidInput],
    'traversal in a panel path' => ['gcego:make-panel', ['name' => 'Staff', '--path' => '../admin'], ExitCode::InvalidInput],
    'an invalid domain' => ['gcego:make-panel', ['name' => 'Staff', '--domain' => 'evil.test/x?'], ExitCode::InvalidInput],
    'an unknown guard' => ['gcego:make-panel', ['name' => 'Staff', '--guard' => 'root'], ExitCode::MissingDependency],
    'an icon with markup' => ['gcego:make-module', ['name' => 'Exams', '--icon' => '<script>'], ExitCode::InvalidInput],
    'a test outside the project' => ['gcego:make-test', ['name' => 'X', '--type' => 'component', '--target' => '/etc/passwd.php'], ExitCode::InvalidInput],
    'a reserved word' => ['gcego:make-blueprint', ['name' => 'Class'], ExitCode::InvalidInput],
]);

it('escapes free text such as titles into valid PHP strings', function (): void {
    gcego('gcego:make-page', ['name' => 'Reports', '--title' => "O'Reilly \\ Reports {{ name }}"]);

    [$page, $errors] = loadDefinition('app/GCEGo/Pages/reports.php', 'page');
    expect($errors)->toBe([])->and($page->title())->toBe("O'Reilly \\ Reports {{ name }}");
});

it('never generates public or unauthorized definitions', function (): void {
    gcego('gcego:make-module', ['name' => 'Academics']);

    foreach ([
        ['gcego:make-resource', ['name' => 'Subject', '--module' => 'academics', '--model' => Subject::class]],
        ['gcego:make-page', ['name' => 'Reports']],
        ['gcego:make-panel', ['name' => 'Staff']],
        ['gcego:make-widget', ['name' => 'Count', '--model' => Subject::class]],
        ['gcego:make-layout', ['name' => 'Board']],
        ['gcego:make-workflow', ['name' => 'Review']],
        ['gcego:make-settings', ['name' => 'Exams']],
        ['gcego:make-dataset', ['name' => 'Facts', '--model' => Subject::class]],
        ['gcego:make-action', ['name' => 'PublishSubject', '--module' => 'academics']],
        ['gcego:make-blueprint', ['name' => 'Subjects', '--model' => Subject::class]],
    ] as [$command, $parameters]) {
        expect(gcego($command, $parameters)[0])->toBe(ExitCode::Success, $command);
    }

    $code = implode("\n", array_map('file_get_contents', generatedFiles()));

    expect($code)->not->toContain('PublicAccess')->not->toContain('Gate::define')->not->toContain('->forceFill(')->not->toContain('new Delete')
        ->and(file_get_contents(ws('app/GCEGo/Panels/staff.php')))->toContain("new Ability('access-staff')")
        ->and(file_get_contents(ws('app/GCEGo/Pages/reports.php')))->toContain('new Authenticated')
        ->and(file_get_contents(ws('app/GCEGo/Widgets/Count.php')))->toContain("new Ability('viewAny', Subject::class)")
        ->and(file_get_contents(ws('app/GCEGo/Settings/exams.php')))->toContain("new Ability('exams.manage')")
        ->and(file_get_contents(ws('app/GCEGo/Analytics/Datasets/facts.php')))->toContain("new Ability('viewAny', Subject::class)")
        ->and(substr_count((string) file_get_contents(ws('app/GCEGo/Workflows/review.php')), 'ability:'))->toBe(3)
        ->and(file_get_contents(ws('app/GCEGo/Modules/Academics/Actions/PublishSubject.php')))->toContain("new Property(Action::Ability, 'publishSubject')")->toContain('Action::Confirmation, true');
});

it('leaves sensitive attributes out of generated fields', function (): void {
    gcego('gcego:make-module', ['name' => 'Academics']);
    gcego('gcego:make-resource', ['name' => 'Subject', '--module' => 'academics', '--model' => Subject::class]);
    gcego('gcego:make-dataset', ['name' => 'Facts', '--model' => Subject::class]);

    foreach (['app/GCEGo/Modules/Academics/Resources/Subjects.php', 'app/GCEGo/Analytics/Datasets/facts.php'] as $file) {
        expect(file_get_contents(ws($file)))->not->toContain("'password'")->not->toContain("'api_token'")->not->toContain("'internal_notes'")->not->toContain("'institution_id'");
    }
});

it('makes no database changes while generating', function (): void {
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    gcego('gcego:make-page', ['name' => 'Reports']);
    gcego('gcego:make-module', ['name' => 'Academics']);
    gcego('gcego:make-resource', ['name' => 'Subject', '--module' => 'academics', '--model' => Subject::class]);

    expect(array_filter($queries, static fn (string $sql): bool => preg_match('/^\s*(insert|update|delete|drop|alter|create|truncate)/i', $sql) === 1))->toBe([]);
});
