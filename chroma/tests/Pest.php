<?php

declare(strict_types=1);

use Gcego\Chroma\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Build');

/**
 * Run a Node script from the package root and decode its JSON output, or skip when Node or Tailwind is unavailable.
 *
 * @param  list<string>  $arguments
 * @return array<string, mixed>
 */
function nodeJson(string $script, array $arguments = []): array
{
    $root = dirname(__DIR__);

    if (trim((string) shell_exec('command -v node')) === '' || ! is_dir(dirname($root, 2).'/node_modules/@tailwindcss/node')) {
        test()->markTestSkipped('Node.js or the monorepo\'s Tailwind CSS v4 packages are not available.');
    }

    $command = 'cd '.escapeshellarg($root).' && node '.escapeshellarg($script).' '.implode(' ', array_map('escapeshellarg', $arguments)).' 2>&1';
    $output = (string) shell_exec($command);
    $decoded = json_decode($output, true);

    if (! is_array($decoded)) {
        test()->fail("Node output was not JSON:\n".$output);
    }

    /** @var array<string, mixed> $decoded */
    return $decoded;
}
