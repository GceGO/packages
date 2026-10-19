<?php

declare(strict_types=1);

use App\Notifications\WelcomeStudent;
use Gcego\Actions\Action;
use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Generators\TestGenerator;
use Gcego\Cli\Tests\Fixtures\Models\Note;
use Gcego\Cli\Tests\Fixtures\Models\Subject;
use Gcego\Components\Component;
use Gcego\Forms\Form;
use Gcego\Infolists\Infolist;
use Gcego\Notifications\Notification;
use Gcego\Tables\Table;

beforeEach(function (): void {
    expect(gcego('gcego:make-module', ['name' => 'Academics'])[0])->toBe(ExitCode::Success);
});

/**
 * Generates through the real command and validates the result with its kind (gcego:validate --id).
 *
 * @param  array<string, mixed>  $parameters
 */
function generates(string $command, array $parameters, string $file, ?string $identity): object
{
    [$code, $output] = gcego($command, $parameters);
    expect($code)->toBe(ExitCode::Success, $output)->and(is_file(ws($file)))->toBeTrue($output);
    PhpToken::tokenize((string) file_get_contents(ws($file)), TOKEN_PARSE);
    expect(file_get_contents(ws($file)))->toStartWith("<?php\n\ndeclare(strict_types=1);");

    if ($identity !== null) {
        [$validated, $report] = gcego('gcego:validate', ['--id' => $identity]);
        expect($validated)->toBe(ExitCode::Success, $report);
    }

    // Class files are left to the autoloader (requiring one twice would redeclare it).
    if (str_contains((string) file_get_contents(ws($file)), "\nnamespace ")) {
        return new stdClass;
    }

    $definition = (static fn (string $path): mixed => require $path)(ws($file));

    return is_object($definition) ? $definition : new stdClass;
}

it('generates valid definitions for every kind', function (string $command, array $parameters, string $file, ?string $identity): void {
    if (($parameters['--type'] ?? null) === 'recent') {
        gcego('gcego:make-blueprint', ['name' => 'Subjects', '--model' => Subject::class]);
    }

    if (str_contains($command, 'metric') || str_contains($command, 'report')) {
        gcego('gcego:make-dataset', ['name' => 'StudentPerformance', '--model' => Subject::class]);
    }

    $definition = generates($command, $parameters, $file, $identity);

    if ($definition instanceof Component) {
        expect($definition->validate()->errors())->toBe([]);
    }
})->with([
    'blueprint' => ['gcego:make-blueprint', ['name' => 'Subjects', '--model' => Subject::class], 'app/GCEGo/Blueprints/Subjects/Subjects.php', 'blueprint:subjects'],
    'blueprint without model' => ['gcego:make-blueprint', ['name' => 'Notices'], 'app/GCEGo/Blueprints/Notices/Notices.php', 'blueprint:notices'],
    'module' => ['gcego:make-module', ['name' => 'Exams', '--description' => 'Exam sessions', '--icon' => 'academic-cap'], 'app/GCEGo/Modules/Exams/Module.php', 'module:exams'],
    'resource' => ['gcego:make-resource', ['name' => 'Subject', '--model' => Subject::class, '--module' => 'academics', '--delete' => true], 'app/GCEGo/Modules/Academics/Resources/Subjects.php', 'module:academics'],
    'read-only resource' => ['gcego:make-resource', ['name' => 'Note', '--model' => Note::class, '--module' => 'academics'], 'app/GCEGo/Modules/Academics/Resources/Notes.php', 'module:academics'],
    'page' => ['gcego:make-page', ['name' => 'Reports', '--ability' => 'view-reports'], 'app/GCEGo/Pages/reports.php', 'page:reports'],
    'dashboard page' => ['gcego:make-page', ['name' => 'Dashboard', '--type' => 'dashboard'], 'app/GCEGo/Pages/dashboard.php', 'page:dashboard'],
    'panel' => ['gcego:make-panel', ['name' => 'Staff', '--module' => ['academics'], '--tenant' => 'optional', '--layout' => 'topbar'], 'app/GCEGo/Panels/staff.php', 'panel:staff'],
    'stat widget' => ['gcego:make-widget', ['name' => 'SubjectCount', '--model' => Subject::class], 'app/GCEGo/Widgets/SubjectCount.php', 'widget:subject_count'],
    'trend widget' => ['gcego:make-widget', ['name' => 'NewSubjects', '--type' => 'trend', '--model' => Subject::class], 'app/GCEGo/Widgets/NewSubjects.php', 'widget:new_subjects'],
    'chart widget' => ['gcego:make-widget', ['name' => 'SubjectsChart', '--type' => 'chart', '--model' => Subject::class], 'app/GCEGo/Widgets/SubjectsChart.php', 'widget:subjects_chart'],
    'progress widget' => ['gcego:make-widget', ['name' => 'SubjectGoal', '--type' => 'progress', '--model' => Subject::class], 'app/GCEGo/Widgets/SubjectGoal.php', 'widget:subject_goal'],
    'recent records widget' => ['gcego:make-widget', ['name' => 'LatestSubjects', '--type' => 'recent', '--blueprint' => 'subjects', '--model' => Subject::class], 'app/GCEGo/Widgets/LatestSubjects.php', 'widget:latest_subjects'],
    'custom widget' => ['gcego:make-widget', ['name' => 'Attendance', '--type' => 'custom', '--ability' => 'view-attendance'], 'app/GCEGo/Widgets/Attendance.php', 'widget:attendance'],
    'layout' => ['gcego:make-layout', ['name' => 'AcademicDashboard', '--columns' => '3'], 'app/GCEGo/Layouts/academic-dashboard.php', 'layout:academic-dashboard'],
    'navigation' => ['gcego:make-navigation', ['name' => 'AcademicNavigation', '--item' => ['Subjects=/staff/subjects', 'Home=/'], '--permission' => 'academics.view'], 'app/GCEGo/Navigation/academic-navigation.php', 'navigation:academic-navigation'],
    'workflow' => ['gcego:make-workflow', ['name' => 'ContentApproval', '--model' => Subject::class], 'app/GCEGo/Workflows/content-approval.php', 'workflow:content-approval'],
    'settings' => ['gcego:make-settings', ['name' => 'AcademicSettings', '--scope' => 'tenant', '--page' => true], 'app/GCEGo/Settings/academic.php', 'settings:academic'],
    'dataset' => ['gcego:make-dataset', ['name' => 'SubjectFacts', '--model' => Subject::class], 'app/GCEGo/Analytics/Datasets/subject-facts.php', 'dataset:subject-facts'],
    'metric on a dataset' => ['gcego:make-metric', ['name' => 'PublishedSubjects', '--dataset' => 'student-performance'], 'app/GCEGo/Analytics/Metrics/published_subjects.php', 'metric:published_subjects'],
    'metric on a model' => ['gcego:make-metric', ['name' => 'TotalCredits', '--model' => Subject::class, '--aggregate' => 'sum', '--field' => 'credits'], 'app/GCEGo/Analytics/Metrics/total_credits.php', 'metric:total_credits'],
    'report' => ['gcego:make-report', ['name' => 'AcademicPerformance', '--dataset' => 'student-performance', '--export' => true], 'app/GCEGo/Analytics/Reports/academic-performance.php', 'report:academic-performance'],
]);

it('generates reusable forms, tables and infolists a resource can require', function (): void {
    $form = generates('gcego:make-form', ['name' => 'SubjectForm', '--module' => 'academics', '--model' => Subject::class], 'app/GCEGo/Modules/Academics/Forms/SubjectForm.php', null);
    $table = generates('gcego:make-table', ['name' => 'SubjectTable', '--module' => 'academics', '--model' => Subject::class], 'app/GCEGo/Modules/Academics/Tables/SubjectTable.php', null);
    $infolist = generates('gcego:make-infolist', ['name' => 'SubjectDetails', '--module' => 'academics'], 'app/GCEGo/Modules/Academics/Infolists/SubjectDetails.php', null);

    expect($form)->toBeInstanceOf(Form::class)->and($form->validate()->errors())->toBe([])
        ->and($table)->toBeInstanceOf(Table::class)->and($table->validate()->errors())->toBe([])
        ->and($infolist)->toBeInstanceOf(Infolist::class)->and($infolist->validate()->errors())->toBe([])
        ->and(array_map(static fn ($field): ?string => $field->name(), iterator_to_array($form->children())))->toBe(['name', 'code', 'description', 'is_published', 'published_on', 'credits', 'status']);

    // The parts plug into a resource: the module stays valid.
    @mkdir(ws('app/GCEGo/Modules/Academics/Resources'));
    file_put_contents(ws('app/GCEGo/Modules/Academics/Resources/Subjects.php'), <<<'PHP'
        <?php

        declare(strict_types=1);

        use Gcego\Actions\Create;
        use Gcego\Data\Source;
        use Gcego\Modules\Resource;
        use Gcego\Cli\Tests\Fixtures\Models\Subject;

        return new Resource(new Source(Subject::class), require dirname(__DIR__).'/Tables/SubjectTable.php', require dirname(__DIR__).'/Forms/SubjectForm.php', new Create);
        PHP);

    expect(gcego('gcego:validate', ['--id' => 'module:academics'])[0])->toBe(ExitCode::Success);
});

it('generates an action handler and its definition', function (): void {
    generates('gcego:make-action', ['name' => 'PublishSubject', '--module' => 'academics'], 'app/GCEGo/Support/Actions/PublishSubject.php', null);
    $action = generates('gcego:make-action', ['name' => 'PublishSubject', '--module' => 'academics'], 'app/GCEGo/Modules/Academics/Actions/PublishSubject.php', null);

    expect($action)->toBeInstanceOf(Action::class)->and($action->validate()->errors())->toBe([])
        ->and($action->get(Action::Ability))->toBe('publishSubject')
        ->and(class_exists('App\\GCEGo\\Support\\Actions\\PublishSubject'))->toBeTrue()
        ->and(new ReflectionClass('App\\GCEGo\\Support\\Actions\\PublishSubject'))->implementsInterface(ActionHandler::class);
});

it('generates a notification class that builds a valid notification', function (): void {
    generates('gcego:make-notification', ['name' => 'WelcomeStudent', '--channels' => 'database,mail', '--type' => 'success'], 'app/Notifications/WelcomeStudent.php', null);

    $notification = WelcomeStudent::make();

    expect($notification->validate()->errors())->toBe([])->and($notification->problems())->toBe([])
        ->and($notification->get(Notification::Channels))->toBe(['database', 'mail']);
});

it('generates tests that run against the generated definitions', function (string $type, string $target, array $setup): void {
    foreach ($setup as $command => $parameters) {
        expect(gcego($command, $parameters)[0])->toBe(ExitCode::Success);
    }

    foreach (['pest', 'phpunit'] as $framework) {
        [$code, $output] = gcego('gcego:make-test', ['name' => ucfirst($type).ucfirst($framework).'Test', '--type' => $type, '--target' => $target, '--'.$framework => true]);
        expect($code)->toBe(ExitCode::Success, $output);
        $file = ws('tests/Feature/'.ucfirst($type).ucfirst($framework).'Test.php');
        PhpToken::tokenize((string) file_get_contents($file), TOKEN_PARSE);
    }

    expect(file_get_contents(ws('tests/Feature/'.ucfirst($type).'PhpunitTest.php')))->toContain('namespace Tests\\Feature;')->toContain('extends TestCase');

    // Run the generated statements, as the generated test would.
    $this->reboot();
    $body = app(TestGenerator::class)->body($type, $target);
    $code = implode("\n", array_map(static fn (string $import): string => 'use '.$import.';', $body['imports']))."\n".implode("\n", $body['lines']);
    eval($code);
})->with([
    'module' => ['module', 'academics', []],
    'resource' => ['resource', 'academics.subjects', ['gcego:make-resource' => ['name' => 'Subject', '--model' => Subject::class, '--module' => 'academics']]],
    'page' => ['page', 'reports', ['gcego:make-page' => ['name' => 'Reports']]],
    'panel' => ['panel', 'staff', ['gcego:make-panel' => ['name' => 'Staff']]],
    'blueprint' => ['blueprint', 'notices', ['gcego:make-blueprint' => ['name' => 'Notices']]],
    'workflow' => ['workflow', 'content-approval', ['gcego:make-workflow' => ['name' => 'ContentApproval', '--model' => Subject::class]]],
    'settings' => ['settings', 'academic', ['gcego:make-settings' => ['name' => 'AcademicSettings']]],
    'analytics' => ['analytics', 'dataset:subject-facts', ['gcego:make-dataset' => ['name' => 'SubjectFacts', '--model' => Subject::class]]],
    'component' => ['component', 'widget:subject_count', ['gcego:make-widget' => ['name' => 'SubjectCount', '--model' => Subject::class]]],
    'security' => ['security', 'access-admin', []],
    'tenant' => ['tenant', Subject::class, []],
]);
