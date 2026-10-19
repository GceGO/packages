<?php

declare(strict_types=1);

use Gcego\Pages\Page;
use Gcego\Pages\Registry\PageKind;
use Gcego\Pages\Tests\Fixtures\Models\Subject;
use Gcego\Pages\Tests\Fixtures\Policies\SubjectPolicy;
use Gcego\Pages\Tests\Fixtures\Providers\SubjectCount;
use Gcego\Registry\Registry;
use Illuminate\Support\Facades\Gate;

/**
 * The README's examples, executed verbatim (App\ classes are the test fixtures).
 *
 * @return array<string, Page>
 */
function documentedExamples(): array
{
    if (! class_exists('App\Models\Subject')) {
        class_alias(Subject::class, 'App\Models\Subject');
        class_alias(SubjectCount::class, 'App\GCEGo\Widgets\SubjectCount');
    }

    // Policies are looked up by the class name the Source names.
    Gate::policy('App\Models\Subject', SubjectPolicy::class);

    preg_match_all('/<!-- example:([a-z]+) -->\n```php\n(.*?)```/s', (string) file_get_contents(dirname(__DIR__, 2).'/README.md'), $blocks, PREG_SET_ORDER);
    $pages = [];

    foreach ($blocks as [, $name, $code]) {
        $page = eval($code);
        $pages[$name] = $page instanceof Page ? $page : throw new LogicException("Example {$name} does not return a page.");
    }

    return $pages;
}

it('documents six levels of pages that are valid definitions', function (): void {
    $pages = documentedExamples();
    $kind = new PageKind;

    expect(array_keys($pages))->toBe(['minimal', 'index', 'create', 'edit', 'view', 'dashboard']);

    foreach ($pages as $name => $page) {
        expect($kind->validate($page))->toBe([], "Example {$name} is invalid.");
    }
});

it('renders every documented example', function (): void {
    $world = world();
    $registry = app(Registry::class);

    foreach (documentedExamples() as $page) {
        $registry->register($page, 'docs');
    }

    actAs($world['admin']);
    $render = static fn (string $page, ?int $record = null): string => squish(pages()->render('page:docs.'.$page, $record)->toHtml());

    expect($render('about'))->toContain('About GCEGO</h1>')->toContain('GCEGO helps candidates prepare for the GCE.')
        ->and($render('subjects'))->toContain('wire:name="gcego-table"')->toContain('Chemistry')
        ->and($render('subjects-create'))->toContain('wire:name="gcego-form"')->toContain('Archived')
        ->and($render('subjects-edit', 1))->toContain('Edit Chemistry</h1>')->toContain('data-gcego-action="delete"')
        ->and($render('subjects-view', 1))->toContain('Chemistry</h1>')->toContain('CHE')
        ->and($render('dashboard'))->toContain('Dashboard</h1>')->toContain('Subjects')->toContain('Reports');
});
