<?php

declare(strict_types=1);

use Gcego\Cli\Commands\Concerns\CommandNames;
use Gcego\Cli\ExitCode;
use Illuminate\Contracts\Console\Kernel;

/**
 * @return array<string, string>
 */
function documents(): array
{
    $root = dirname(__DIR__, 2);
    $documents = ['README.md' => (string) file_get_contents($root.'/README.md')];

    foreach (glob($root.'/docs/*.md') ?: [] as $file) {
        $documents['docs/'.basename($file)] = (string) file_get_contents($file);
    }

    return $documents;
}

it('mentions only commands that exist', function (): void {
    $registered = array_keys(app(Kernel::class)->all());

    foreach (documents() as $name => $text) {
        preg_match_all('/gcego:[a-z][a-z:-]*[a-z]/', $text, $matches);

        foreach (array_unique($matches[0]) as $command) {
            expect(in_array($command, $registered, true) || str_starts_with($command, 'gcego:make-') && in_array('gcego:make-'.substr($command, 11), $registered, true))->toBeTrue("{$name} mentions {$command}, which is not a command.");
        }
    }
});

it('documents every command and the exit codes as implemented', function (): void {
    $readme = documents()['README.md'];

    foreach (array_keys(CommandNames::Commands) as $command) {
        $short = str_starts_with($command, 'gcego:make-') ? '-'.substr($command, 11) : $command;
        expect(str_contains($readme, $command) || str_contains($readme, '`'.$short.'`'))->toBeTrue("README does not document {$command}.");
    }

    foreach (ExitCode::descriptions() as $code => $description) {
        expect($readme)->toMatch('/\| '.$code.' \| '.preg_quote(explode(' ', $description)[0], '/').'/i');
    }
});
