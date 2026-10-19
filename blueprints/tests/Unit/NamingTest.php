<?php

declare(strict_types=1);

use Gcego\Blueprints\Support\Naming;

it('slugifies deterministically', function (string $input, string $slug): void {
    expect(Naming::slugify($input))->toBe($slug);
})->with([
    ['Subject Management', 'subject-management'],
    ['academic_subjects', 'academic-subjects'],
    ['  GCE  Papers 2024 ', 'gce-papers-2024'],
    ['a--b__c', 'a-b-c'],
]);

it('humanizes names', function (): void {
    expect(Naming::humanize('academic-subjects'))->toBe('Academic Subjects')
        ->and(Naming::humanize('exam_papers'))->toBe('Exam Papers');
});

it('validates identifiers', function (): void {
    expect(Naming::isName('subjects'))->toBeTrue()
        ->and(Naming::isName(str_repeat('a', 101)))->toBeFalse()
        ->and(Naming::isName(5))->toBeFalse()
        ->and(Naming::isSlug('subject-management'))->toBeTrue()
        ->and(Naming::isSlug('subject_management'))->toBeFalse()
        ->and(Naming::isIcon('heroicon-o-book-open'))->toBeTrue()
        ->and(Naming::isIcon('lucide:book'))->toBeTrue()
        ->and(Naming::isTitle(str_repeat('x', 201)))->toBeFalse();
});
