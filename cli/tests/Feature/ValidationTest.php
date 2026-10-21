<?php

declare(strict_types=1);

use Gcego\Cli\ExitCode;
use Gcego\Cli\Tests\Fixtures\Models\Subject;

beforeEach(function (): void {
    gcego('gcego:make-module', ['name' => 'Academics']);
    gcego('gcego:make-resource', ['name' => 'Subject', '--module' => 'academics', '--model' => Subject::class]);
    gcego('gcego:make-workflow', ['name' => 'Review']);
});

it('validates every definition with its package\'s validators', function (): void {
    [$code, $output] = gcego('gcego:validate');
    $report = gcegoJson('gcego:validate');

    expect($code)->toBe(ExitCode::Success)->and($output)->toContain('module:academics')
        ->and($report['valid'])->toBeTrue()->and($report['summary']['definitions'])->toBe(7)
        ->and(array_keys($report['entries'][0]))->toBe(['identity', 'kind', 'source', 'valid', 'problems']);
});

it('reports missing references between definitions', function (): void {
    @mkdir(ws('app/GCEGo/Panels'), 0777, true);
    file_put_contents(ws('app/GCEGo/Panels/staff.php'), "<?php\nuse Gcego\\Components\\Properties\\Property;\nuse Gcego\\Panels\\Panel;\nreturn new Panel(new Property(Panel::Name, 'staff'), new Gcego\\Security\\Requirements\\Authenticated, new Gcego\\Panels\\Definitions\\ModuleReference('missing'));\n");

    $report = gcegoJson('gcego:validate', ['--type' => 'panels']);

    expect($report['valid'])->toBeFalse()->and($report['entries'][0]['identity'])->toBe('panel:staff')
        ->and($report['entries'][0]['problems'][0]['message'])->toContain('"missing"');
});

it('reports invalid definitions and duplicates with exit code 3', function (): void {
    copy(ws('app/GCEGo/Workflows/review.php'), ws('app/GCEGo/Workflows/review-copy.php'));
    file_put_contents(ws('app/GCEGo/Workflows/broken.php'), "<?php\nreturn new Gcego\\Workflows\\Workflow('broken', new Gcego\\Workflows\\States\\State('a'), new Gcego\\Workflows\\Transitions\\Transition('go', 'a', 'nowhere'));\n");

    $report = gcegoJson('gcego:validate');
    $codes = collect($report['entries'])->flatMap(fn (array $entry): array => array_column($entry['problems'], 'code'))->unique()->values()->all();

    expect($report['valid'])->toBeFalse()->and($report['exit_code'])->toBe(ExitCode::ValidationFailed)
        ->and($codes)->toContain('registration.duplicate', 'registration.failed')
        ->and($report['notices'][0])->toContain('Integration checks')
        ->and(gcego('gcego:validate')[0])->toBe(ExitCode::ValidationFailed);
});

it('validates one type or one definition', function (): void {
    expect(gcegoJson('gcego:validate', ['--type' => 'workflows'])['summary']['definitions'])->toBe(1)
        ->and(gcegoJson('gcego:validate', ['--type' => 'resources'])['entries'][0]['identity'])->toBe('module:academics')
        ->and(gcegoJson('gcego:validate', ['--id' => 'workflow:review'])['entries'])->toHaveCount(1)
        ->and(gcego('gcego:validate', ['--type' => 'forms'])[0])->toBe(ExitCode::InvalidInput)
        ->and(gcego('gcego:validate', ['--id' => 'workflow:nope'])[0])->toBe(ExitCode::MissingDependency);
});
