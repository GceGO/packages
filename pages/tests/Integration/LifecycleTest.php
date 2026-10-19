<?php

declare(strict_types=1);

use Gcego\Pages\Components\Content;
use Gcego\Pages\Context\PageContext;
use Gcego\Pages\Context\RecordPageContext;
use Gcego\Pages\Exceptions\InvalidPageException;
use Gcego\Pages\Lifecycle\PageHooks;
use Gcego\Pages\Lifecycle\PageLifecycle;
use Gcego\Pages\Page;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Pages\Tests\Fixtures\Hooks\RecordingHook;
use Gcego\Registry\Registry;
use Gcego\Security\Requirements\Authenticated;
use Illuminate\Http\Request;

beforeEach(function (): void {
    $this->world = world();
    RecordingHook::$events = [];
    pages()->hook(RecordingHook::class);
});

it('runs the stages in order and tells hooks after each one', function (): void {
    actAs($this->world['admin']);
    pages()->render('subjects.view', 1);

    expect(RecordingHook::$events)->toBe([
        'resolved:page:subjects-view',
        'context_created:page:subjects-view',
        'authorized:page:subjects-view',
        'record_resolved:page:subjects-view:1',
        'prepared:page:subjects-view:1',
        'rendering:page:subjects-view:1',
        'rendered:page:subjects-view:1',
        'completed:page:subjects-view:1',
    ]);
});

it('never tells hooks about a page or record the user may not open', function (string $page, string|int|null $record, string $user, array $events): void {
    actAs($this->world[$user]);

    expect(statusOf(fn () => pages()->open($page, $record)))->toBeIn([403, 404])
        ->and(RecordingHook::$events)->toBe($events);
})->with([
    'page denied' => ['subjects', null, 'visitor', ['resolved:page:subjects', 'context_created:page:subjects']],
    'record denied' => ['subjects.edit', 1, 'reader', ['resolved:page:subjects-edit', 'context_created:page:subjects-edit', 'authorized:page:subjects-edit']],
    'record out of scope' => ['subjects.view', 4, 'admin', ['resolved:page:subjects-view', 'context_created:page:subjects-view', 'authorized:page:subjects-view']],
]);

it('builds a typed, request-scoped context', function (): void {
    actAs($this->world['admin']);
    $context = pages()->open('subjects.view', '1');

    expect($context)->toBeInstanceOf(RecordPageContext::class)
        ->and($context->identity)->toBe('page:subjects-view')
        ->and($context->type())->toBe('view')
        ->and($context->route)->toBe('gcego.subjects.view')
        ->and($context->user?->getAuthIdentifier())->toBe($this->world['admin']->id)
        ->and($context->tenant)->toBe('1')
        ->and($context->locale)->toBe('en')
        ->and($context->timezone)->toBe('UTC')
        ->and($context->recordKey())->toBe('1')
        ->and($context->label)->toBe('Chemistry')
        ->and($context->header?->title->text)->toBe('Chemistry')
        ->and($context->header?->description?->text)->toBe('Subject Chemistry')
        ->and($context->header?->breadcrumbs->toArray())->toBe([
            ['label' => 'Subjects', 'url' => '/admin/subjects', 'current' => false],
            ['label' => 'Chemistry', 'url' => '/admin/subjects/1', 'current' => true],
        ])
        ->and($context->header?->actions)->toBe(['#0.#1'])
        ->and(pages()->open('subjects'))->toBeInstanceOf(PageContext::class)
        ->and(pages()->open('subjects')->hasRecord())->toBeFalse();
});

it('validates route parameters against the page', function (): void {
    actAs($this->world['admin']);

    expect(pages()->open('institutions.members', null, ['institution' => '1'])->parameters)->toBe(['institution' => '1'])
        ->and(statusOf(fn () => pages()->open('institutions.members', null, ['institution' => 'one'])))->toBe(404)
        ->and(statusOf(fn () => pages()->open('institutions.members')))->toBe(404)
        ->and(statusOf(fn () => pages()->open('institutions.members', null, ['institution' => ['1']])))->toBe(404)
        ->and(pages()->open('subjects', null, ['unexpected' => 'x'])->parameters)->toBe([]);
});

it('opens a page once per request, and again for the next request', function (): void {
    actAs($this->world['admin']);
    $first = null;
    $queries = queriesDuring(function () use (&$first): void {
        $first = pages()->open('subjects.view', 1);
    });
    $again = queriesDuring(fn () => expect(pages()->open('subjects.view', '1'))->toBe($first));

    app()->instance('request', Request::create('/admin/subjects/1'));
    $next = pages()->open('subjects.view', 1);

    expect($queries)->not->toBe([])
        ->and($again)->toBe([])
        ->and($next)->not->toBe($first)
        ->and($next->header?->title->text)->toBe('Chemistry');
});

it('rejects hooks that are not lifecycle hooks', function (): void {
    expect(fn () => app(PageHooks::class)->add(stdClass::class))->toThrow(InvalidPageException::class);
});

it('marks Livewire updates as interactions', function (): void {
    actAs($this->world['admin']);
    app(PageLifecycle::class)->open('subjects', interaction: true);

    expect(RecordingHook::$events)->toContain('interaction:page:subjects');
});

it('resolves related pages within the registry scope of the page', function (): void {
    actAs($this->world['admin']);
    $registry = app(Registry::class);
    $registry->register(new Page('dashboard', 'School home', new Authenticated, new Content('Home.')), 'school');
    $registry->register(new Page('dashboard.report', 'Report', new Authenticated, new Content('Report.')), 'school');
    $definitions = app(PageDefinitions::class);

    expect($definitions->identity('dashboard.report', 'page:school.dashboard'))->toBe('page:school.dashboard-report')
        ->and($definitions->identity('page:school.dashboard'))->toBe('page:school.dashboard')
        ->and(PageDefinitions::scopeOf('page:school.dashboard'))->toBe('school')
        ->and(PageDefinitions::scopeOf('page:dashboard'))->toBeNull()
        ->and(pages()->open('page:school.dashboard-report')->header?->breadcrumbs->toArray())->toBe([
            ['label' => 'School home', 'url' => null, 'current' => false],
            ['label' => 'Report', 'url' => null, 'current' => true],
        ]);
});
