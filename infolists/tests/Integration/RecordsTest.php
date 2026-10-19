<?php

declare(strict_types=1);

use Gcego\Infolists\Tests\Fixtures\Models\Category;
use Gcego\Infolists\Tests\Fixtures\Models\Course;
use Gcego\Infolists\Tests\Fixtures\Models\Document;
use Gcego\Infolists\Tests\Fixtures\Models\Report;
use Gcego\Infolists\Tests\Fixtures\Models\Subject;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

it('resolves records by integer, UUID, ULID and custom string keys', function (): void {
    $document = Document::query()->create(['title' => 'Policy', 'institution_id' => 1]);
    $report = Report::query()->create(['title' => 'Q1 report', 'institution_id' => 1]);
    $course = Course::query()->create(['course_code' => 'MTH-A', 'title' => 'Maths A']);

    expect(details('subjects', $this->world['subject']->id))->toContain('Algebra')
        ->and(details('subjects', (string) $this->world['subject']->id))->toContain('Algebra')
        ->and(details('documents', $document->id))->toContain('Policy')
        ->and(details('reports', $report->id))->toContain('Q1 report')
        ->and(details('courses', 'MTH-A'))->toContain('Maths A');
});

it('treats missing, other-tenant and forged keys as not found', function (string|int $key): void {
    expect(statusOf(fn () => infolists()->render('subjects', $key === 'south' ? $this->world['south']->id : $key)))->toBe(404);
})->with([999999, 'south', '1 OR 1=1', '', '../../etc/passwd']);

it('respects soft deletes unless the infolist opts in', function (): void {
    $this->world['subject']->delete();

    expect(statusOf(fn () => infolists()->render('subjects', $this->world['subject']->id)))->toBe(404)
        ->and(details('archive', $this->world['subject']->id))->toContain('Algebra');
});

it('checks the view ability and the blueprint requirements', function (): void {
    Gate::before(fn ($user, string $ability) => $ability === 'view' ? false : null);

    expect(statusOf(fn () => infolists()->render('subjects', $this->world['subject']->id)))->toBe(403);

    Gate::before(fn ($user, string $ability) => $ability === 'viewAny' ? false : null);

    expect(statusOf(fn () => infolists()->render('minimal', $this->world['subject']->id)))->toBe(403);
});

it('never resolves infolists by class or unknown identity', function (string $definition): void {
    expect(fn () => infolists()->resolve($definition))->toThrow(Exception::class);
})->with([Subject::class, 'blueprint:nope', '../subjects']);

it('eager-loads every relation once, without per-entry queries', function (): void {
    $queries = queriesDuring(fn () => details('subjects', $this->world['subject']->id));
    $sql = implode("\n", $queries);

    expect(substr_count($sql, 'from "topics"'))->toBe(1)
        ->and(substr_count($sql, 'from "teachers"'))->toBe(1)
        ->and(substr_count($sql, 'from "categories"'))->toBe(1)
        ->and(substr_count($sql, 'from "tags"'))->toBe(1)
        ->and(count($queries))->toBeLessThanOrEqual(6);
});

it('applies the related model\'s tenant scope to eager-loaded relations', function (): void {
    $this->world['subject']->update(['category_id' => $this->world['categories']['south']->id]);

    expect(details('subjects', $this->world['subject']->id))->not->toContain('South only');
});

it('shows relationship values, nested relations and collections', function (): void {
    $html = details('subjects', $this->world['subject']->id);

    expect($html)->toContain('Science')
        ->and($html)->toContain('Ada Lovelace')
        ->and($html)->toContain('Alan Turing')
        ->and($html)->toContain('data-gcego-item="1"')
        ->and($html)->toContain('Core')
        ->and($html)->toContain('Lab')
        ->and($html)->toContain('Solve')
        ->and($html)->toContain('Grading scale');
});

it('shows placeholders for empty relations', function (): void {
    $this->world['subject']->topics()->delete();
    $this->world['subject']->update(['category_id' => null, 'objectives' => []]);

    $html = details('subjects', $this->world['subject']->id);

    expect($html)->toContain('data-gcego-entry="category.name"')
        ->and($html)->toContain('—');
});

it('resolves no record and builds no query when rendering is not requested', function (): void {
    expect(queriesDuring(function (): void {
        infolists()->resolve('subjects');
        infolists()->source(infolists()->resolve('subjects'));
        Category::class;
    }))->toBe([]);
});
