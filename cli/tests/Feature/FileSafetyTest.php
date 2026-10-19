<?php

declare(strict_types=1);

use Gcego\Cli\ExitCode;
use Gcego\Cli\Generation\FileOperation;
use Gcego\Cli\Generation\GenerationPlan;
use Gcego\Cli\Generation\GenerationTransaction;
use Gcego\Cli\Generation\PlannedFile;
use Gcego\Cli\Tests\Fixtures\Models\Subject;

function leftovers(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ws(), FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (str_ends_with((string) $file, '.gcego-tmp')) {
            $files[] = (string) $file;
        }
    }

    return $files;
}

it('previews with --dry-run and writes nothing', function (): void {
    $plan = gcegoJson('gcego:make-panel', ['name' => 'Staff', '--dry-run' => true]);

    expect($plan['dry_run'])->toBeTrue()->and($plan['successful'])->toBeTrue()->and($plan['created'])->toBe(['app/GCEGo/Panels/staff.php'])
        ->and($plan['files'][0])->toMatchArray(['path' => 'app/GCEGo/Panels/staff.php', 'operation' => 'create'])
        ->and($plan['next_steps'][0])->toContain("Gate::define('access-staff'")
        ->and(is_dir(ws('app/GCEGo/Panels')))->toBeFalse();

    [$code, $output] = gcego('gcego:make-panel', ['name' => 'Staff', '--dry-run' => true]);
    expect($code)->toBe(ExitCode::Success)->and($output)->toContain('would create')->toContain('nothing written');
});

it('never overwrites a different file without --force, and says what to do', function (): void {
    gcego('gcego:make-page', ['name' => 'Reports']);
    file_put_contents(ws('app/GCEGo/Pages/reports.php'), "<?php\n// mine\n");

    [$code, $output] = gcego('gcego:make-page', ['name' => 'Reports', '--title' => 'Monthly']);
    expect($code)->toBe(ExitCode::FileConflict)->and($output)->toContain('Nothing was written')->and(file_get_contents(ws('app/GCEGo/Pages/reports.php')))->toBe("<?php\n// mine\n");

    // A name another file already defines is refused outright.
    gcego('gcego:make-page', ['name' => 'Help']);
    rename(ws('app/GCEGo/Pages/help.php'), ws('app/GCEGo/Pages/elsewhere.php'));
    [$code, $output] = gcego('gcego:make-page', ['name' => 'Help']);
    expect($code)->toBe(ExitCode::InvalidInput)->and($output)->toContain('"page:help" is already defined in app/GCEGo/Pages/elsewhere.php');
});

it('reports conflicts with exit code 4 and overwrites deliberately with --force', function (): void {
    @mkdir(ws('app/GCEGo/Workflows'), 0777, true);
    file_put_contents(ws('app/GCEGo/Workflows/content-approval.php'), "<?php\n// mine\nreturn null;\n");

    [$code, $output] = gcego('gcego:make-workflow', ['name' => 'ContentApproval']);
    expect($code)->toBe(ExitCode::FileConflict)->and($output)->toContain('GCEGO GENERATION FAILED')->toContain('app/GCEGo/Workflows/content-approval.php')->toContain('Use --force to overwrite it deliberately.')
        ->and(file_get_contents(ws('app/GCEGo/Workflows/content-approval.php')))->toContain('// mine');

    $forced = gcegoJson('gcego:make-workflow', ['name' => 'ContentApproval', '--force' => true]);
    expect($forced['overwritten'])->toBe(['app/GCEGo/Workflows/content-approval.php'])->and(file_get_contents(ws('app/GCEGo/Workflows/content-approval.php')))->toContain('new Workflow(');
});

it('leaves identical files alone (idempotent re-runs)', function (): void {
    gcego('gcego:make-layout', ['name' => 'Board']);
    $again = gcegoJson('gcego:make-layout', ['name' => 'Board', '--force' => true]);

    expect($again['skipped'])->toBe(['app/GCEGo/Layouts/board.php'])->and($again['created'])->toBe([])->and($again['overwritten'])->toBe([]);
});

it('writes multi-file generations all or nothing, and cleans up temporary files', function (): void {
    @mkdir(ws('app/GCEGo/Panels'), 0777, true);
    file_put_contents(ws('app/GCEGo/Panels/kept.php'), "<?php\n// original\n");
    @mkdir(ws('app/GCEGo/Blocked'), 0555, true);
    $plan = new GenerationPlan('test', [
        new PlannedFile(ws('app/GCEGo/Pages/new.php'), 'app/GCEGo/Pages/new.php', "<?php\n// new\n", 'New file'),
        new PlannedFile(ws('app/GCEGo/Panels/kept.php'), 'app/GCEGo/Panels/kept.php', "<?php\n// replaced\n", 'Overwritten file', FileOperation::Overwrite),
        new PlannedFile(ws('app/GCEGo/Blocked/fail.php'), 'app/GCEGo/Blocked/fail.php', "<?php\n", 'Unwritable file'),
    ]);

    $result = app(GenerationTransaction::class)->commit($plan);
    chmod(ws('app/GCEGo/Blocked'), 0755);

    expect($result->successful)->toBeFalse()->and($result->errors[0])->toContain('Nothing was changed.')
        ->and(is_file(ws('app/GCEGo/Pages/new.php')))->toBeFalse()
        ->and(is_dir(ws('app/GCEGo/Pages')))->toBeFalse()
        ->and(file_get_contents(ws('app/GCEGo/Panels/kept.php')))->toBe("<?php\n// original\n")
        ->and(leftovers())->toBe([]);
})->skip(fn (): bool => function_exists('posix_getuid') && posix_getuid() === 0, 'root ignores directory permissions');

it('rolls back files already moved into place when a later move fails', function (): void {
    @mkdir(ws('app/GCEGo/Pages'), 0777, true);
    file_put_contents(ws('app/GCEGo/Pages/kept.php'), "<?php\n// original\n");
    // The second target is a directory, so moving a file onto it fails after the first was replaced.
    @mkdir(ws('app/GCEGo/Pages/clash.php'), 0777, true);
    $plan = new GenerationPlan('test', [
        new PlannedFile(ws('app/GCEGo/Pages/kept.php'), 'app/GCEGo/Pages/kept.php', "<?php\n// replaced\n", 'Overwrite', FileOperation::Overwrite),
        new PlannedFile(ws('app/GCEGo/Pages/clash.php'), 'app/GCEGo/Pages/clash.php', "<?php\n", 'Clash'),
    ]);

    $result = app(GenerationTransaction::class)->commit($plan);

    expect($result->successful)->toBeFalse()->and(file_get_contents(ws('app/GCEGo/Pages/kept.php')))->toBe("<?php\n// original\n")->and(leftovers())->toBe([]);
});

it('refuses symbolic links and paths outside the application at write time', function (): void {
    @mkdir(ws('app/GCEGo'), 0777, true);
    $outside = sys_get_temp_dir().'/gcego-cli-target-'.getmypid();
    @mkdir($outside, 0777, true);
    symlink($outside, ws('app/GCEGo/Pages'));

    [$code, $output] = gcego('gcego:make-page', ['name' => 'Reports']);

    expect($code)->toBe(ExitCode::InvalidInput)->and($output)->toContain('symbolic link')->and(glob($outside.'/*'))->toBe([]);
    unlink(ws('app/GCEGo/Pages'));
    rmdir($outside);
});

it('asks for missing options interactively and never prompts without interaction', function (): void {
    gcego('gcego:make-module', ['name' => 'Academics']);

    $this->artisan('gcego:make-resource', ['name' => 'Subject'])
        ->expectsChoice('Which module?', 'academics', ['academics'])
        ->expectsQuestion('Which Eloquent model?', Subject::class)
        ->assertExitCode(ExitCode::Success);
    expect(is_file(ws('app/GCEGo/Modules/Academics/Resources/Subjects.php')))->toBeTrue();

    [$code, $output] = gcego('gcego:make-resource', ['name' => 'Topic']);
    expect($code)->toBe(ExitCode::InvalidInput)->and($output)->toContain('The --module option is required');
});

it('refuses to write in production unless confirmed interactively', function (): void {
    $this->reboot(['app.env' => 'production']);

    [$code, $output] = gcego('gcego:make-page', ['name' => 'Reports']);
    expect($code)->toBe(ExitCode::InvalidInput)->and($output)->toContain('Refusing to write files in production')->and(is_file(ws('app/GCEGo/Pages/reports.php')))->toBeFalse();

    $this->artisan('gcego:make-page', ['name' => 'Reports'])->expectsConfirmation('The application is in production. Write the generated files anyway?', 'yes')->assertExitCode(ExitCode::Success);
    expect(is_file(ws('app/GCEGo/Pages/reports.php')))->toBeTrue();

    // Read-only commands still work in production.
    expect(gcego('gcego:list')[0])->toBe(ExitCode::Success);
});
