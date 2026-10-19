<?php

declare(strict_types=1);

/**
 * Every example marked <!-- example:name --> in README.md and docs/*.md,
 * keyed "file#name".
 *
 * @return array<string, string>
 */
function documentedExamples(): array
{
    $root = dirname(__DIR__, 2);
    $examples = [];

    foreach ([$root.'/README.md', ...(glob($root.'/docs/*.md') ?: [])] as $file) {
        preg_match_all('/<!-- example:([a-z0-9-]+) -->\s*```php\n(.*?)```/s', (string) file_get_contents($file), $matches, PREG_SET_ORDER);

        foreach ($matches as [, $name, $code]) {
            $examples[basename($file).'#'.$name] = $code;
        }
    }

    return $examples;
}

it('finds the documented examples', function (): void {
    expect(count(documentedExamples()))->toBeGreaterThanOrEqual(5);
});

it('runs every documented example as written', function (string $code): void {
    $world = world();

    (static function (array $variables, string $code): void {
        extract($variables);
        eval($code);
    })([...$world, 'world' => $world, 'test' => $this], $code);

    expect(true)->toBeTrue();
})->with(fn (): array => array_map(static fn (string $code): array => [$code], documentedExamples()));
