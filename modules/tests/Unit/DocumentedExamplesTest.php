<?php

declare(strict_types=1);

use Gcego\Modules\Compilation\ModuleCompiler;
use Gcego\Modules\Module;
use Gcego\Modules\Operation;
use Gcego\Modules\Tests\Fixtures\Models\Note;
use Gcego\Modules\Tests\Fixtures\Models\Subject;
use Gcego\Modules\Tests\Fixtures\Models\Topic;
use Gcego\Pages\Registry\PageKind;

/**
 * The README's marked examples, evaluated as written (App\Models classes are fixtures).
 */
function readmeExample(string $name): Module
{
    foreach (['App\\Models\\Subject' => Subject::class, 'App\\Models\\Topic' => Topic::class, 'App\\Models\\Student' => Note::class] as $alias => $class) {
        if (! class_exists($alias)) {
            class_alias($class, $alias);
        }
    }

    $readme = (string) file_get_contents(dirname(__DIR__, 2).'/README.md');
    preg_match('/<!-- example:'.$name.' -->\s*```php\n(.*?)```/s', $readme, $match);
    $module = eval($match[1] ?? 'return null;');

    return $module instanceof Module ? $module : throw new RuntimeException('Example '.$name.' did not return a Module.');
}

it('compiles every README example into valid pages', function (string $example, array $pages): void {
    $module = readmeExample($example);
    $derived = app(ModuleCompiler::class)->pages($module);

    expect(array_map(static fn ($page): string => $page->pageName(), $derived))->toBe($pages);

    foreach ($derived as $page) {
        expect((new PageKind)->validate($page))->toBe([]);
    }
})->with([
    'minimal' => ['minimal', []],
    'multiple' => ['multiple', ['academics.subjects', 'academics.topics']],
    'students' => ['students', ['students', 'students.create', 'students.edit']],
    'complete' => ['complete', [
        'academics.subjects', 'academics.subjects.create', 'academics.subjects.view', 'academics.subjects.edit',
        'academics.subjects.topics', 'academics.subjects.topics.create', 'academics.subjects.topics.view', 'academics.subjects.topics.edit',
        'academics.topics', 'academics.topics.create', 'academics.topics.view', 'academics.topics.edit',
        'academics.overview',
    ]],
]);

it('names the minimal example\'s module after its model', function (): void {
    $compiled = app(ModuleCompiler::class)->compile(readmeExample('minimal'));

    expect($compiled->name)->toBe('subjects')
        ->and($compiled->resources[0]->operations)->toBe([])
        ->and(Operation::cases())->toHaveCount(4);
});
