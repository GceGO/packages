<?php

declare(strict_types=1);

use Gcego\Cli\Commands\Concerns\CommandNames;
use Gcego\Cli\Diagnostics\Doctor;
use Gcego\Cli\ExitCode;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;

it('registers every command under its own name, with help', function (): void {
    $all = app(Kernel::class)->all();

    foreach (CommandNames::Commands as $name => $class) {
        expect($all)->toHaveKey($name)->and($all[$name])->toBeInstanceOf($class)
            ->and($all[$name]->getDescription())->not->toBe('');
    }

    expect(count(CommandNames::Commands))->toBe(30);
    [$code, $help] = gcego('help', ['command_name' => 'gcego:make-resource']);
    expect($code)->toBe(0)->and($help)->toContain('--model')->toContain('--dry-run')->toContain('--force');
});

it('redefines no command another GCEGO package owns, and delegates to them', function (): void {
    $owned = [];

    foreach (glob(dirname(__DIR__, 3).'/*/src/{,*/,*/*/}*.php', GLOB_BRACE) ?: [] as $file) {
        if (str_contains($file, '/cli/') || preg_match("/signature = '([a-z:-]+)/", (string) file_get_contents($file), $match) !== 1) {
            continue;
        }

        $owned[$match[1]] = $file;
    }

    expect(array_intersect_key(CommandNames::Commands, $owned))->toBe([])
        ->and(array_keys(array_intersect_key(CommandNames::Delegated, $owned)))->toBe(['gcego:install', 'gcego:registry-cache', 'gcego:registry-clear', 'gcego:studio:health'])
        ->and(app(Kernel::class)->all())->toHaveKeys(array_keys(CommandNames::Delegated));
});

it('detects a command shadowing one of the CLI\'s names', function (): void {
    app(Kernel::class)->registerCommand(new class extends Command
    {
        protected $signature = 'gcego:routes';

        public function handle(): int
        {
            return 0;
        }
    });

    expect(app(Doctor::class)->run()->result('cli.commands')?->status->value)->toBe('failure');
});

it('documents its exit codes and returns them', function (): void {
    expect(ExitCode::descriptions())->toHaveCount(7)
        ->and(gcego('gcego:make-page', ['name' => '../x'])[0])->toBe(ExitCode::InvalidInput)
        ->and(gcego('gcego:make-resource', ['name' => 'Subject', '--module' => 'none', '--model' => 'App\\Nope'])[0])->toBe(ExitCode::MissingDependency)
        ->and(gcego('gcego:list', ['--format' => 'xml'])[0])->toBe(ExitCode::InvalidInput)
        ->and(gcego('gcego:inspect', ['identity' => 'module:nothing'])[0])->toBe(ExitCode::MissingDependency)
        ->and(gcego('gcego:make', ['generator' => 'unknown', 'name' => 'X'])[0])->toBe(ExitCode::InvalidInput);
});

it('writes clean, versioned JSON for automation', function (string $command, array $parameters): void {
    [, $output] = gcego($command, [...$parameters, '--format' => 'json']);

    expect($output)->not->toContain("\e[")
        ->and(json_decode($output, true, flags: JSON_THROW_ON_ERROR))->toBeArray()->toHaveKey('schema', 1);
})->with([
    ['gcego:about', []], ['gcego:doctor', []], ['gcego:list', []], ['gcego:registry', []], ['gcego:validate', []],
    ['gcego:routes', []], ['gcego:assets', []], ['gcego:packages', []], ['gcego:cache-status', []],
    ['gcego:make-page', ['name' => 'Reports', '--dry-run' => true]], ['gcego:make', ['--list' => true]], ['gcego:inspect', ['identity' => 'module:missing']],
]);

it('summarizes the installation', function (): void {
    $about = gcegoJson('gcego:about');

    expect($about['studio'])->toBe('0.1.0')->and($about['cli'])->toBe('0.1.0')->and($about['packages']['integrated'])->toBeGreaterThanOrEqual(18)
        ->and($about['commands'])->toContain('gcego:doctor', 'gcego:install', 'gcego:make-resource');
});
