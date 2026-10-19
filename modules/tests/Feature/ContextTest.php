<?php

declare(strict_types=1);

use Gcego\Modules\Operation;

beforeEach(function (): void {
    $this->world = world();
});

it('describes the current page\'s module, resource, operation and record', function (): void {
    actAs($this->world['admin']);
    onPage('/admin/subjects/1/edit');
    $context = modules()->current();

    expect($context?->module->name())->toBe('academics')
        ->and($context?->module->title())->toBe('Academic Management')
        ->and($context?->resource->name)->toBe('subjects')
        ->and($context?->operation)->toBe(Operation::Edit)
        ->and($context?->recordKey)->toBe('1')
        ->and($context?->module->tenant)->toBe('1')
        ->and($context?->identity())->toBe('resource:academics.subjects')
        ->and($context?->isRelation())->toBeFalse();
});

it('describes relation pages with their parent', function (): void {
    actAs($this->world['admin']);
    onPage('/admin/subjects/1/topics/2');
    $context = modules()->current();

    expect($context?->relation?->name)->toBe('topics')
        ->and($context?->parentKey)->toBe('1')
        ->and($context?->recordKey)->toBe('2')
        ->and($context?->identity())->toBe('relation:academics.subjects.topics');
});

it('knows module pages and nothing outside modules', function (): void {
    actAs($this->world['admin']);
    onPage('/admin/academics/overview');

    expect(modules()->currentModule()?->name())->toBe('academics')
        ->and(modules()->current())->toBeNull();

    app()->forgetScopedInstances();
    onPage('/login');
    expect(modules()->currentModule())->toBeNull();
});

it('is request-scoped: a new request starts without the previous context', function (): void {
    actAs($this->world['admin']);
    onPage('/admin/subjects/1');
    expect(modules()->current()?->recordKey)->toBe('1');

    app()->forgetScopedInstances();
    onPage('/admin/topics/2');
    expect(modules()->current()?->resource->name)->toBe('topics')
        ->and(modules()->current()?->recordKey)->toBe('2');
});
