<?php

declare(strict_types=1);

use Gcego\Actions\Execution\ActionStatus;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Tests\Fixtures\Models\Subject;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['admin']);
});

it('stores and attaches uploads when a record is created through its form', function (): void {
    $result = runAction('page:academics-subjects-create', 'create', input: [
        'name' => 'Astronomy', 'code' => 'AST',
        'cover' => fixtureFile('png', 'sky.png', 400, 300),
        'attachments' => [fixtureFile('pdf', 'syllabus.pdf'), fixtureFile('txt', 'reading.txt')],
    ]);
    $subject = Subject::query()->where('name', 'Astronomy')->firstOrFail();

    expect($result->status)->toBe(ActionStatus::Success)
        ->and($subject->mediaIn('cover')->pluck('original_name')->all())->toBe(['sky.png'])
        ->and($subject->mediaIn('attachments')->pluck('original_name')->all())->toBe(['syllabus.pdf', 'reading.txt'])
        ->and($subject->getAttributes())->not->toHaveKey('cover');
});

it('validates file content, type and count on the field', function (): void {
    $result = runAction('page:academics-subjects-create', 'create', input: [
        'name' => 'Broken', 'code' => 'BRK',
        'cover' => fixtureFile('pdf', 'not-an-image.pdf'),
        'attachments' => [fixtureFile('php', 'shell.pdf')],
    ]);

    expect($result->status)->toBe(ActionStatus::ValidationFailed)
        ->and($result->errors['cover'][0])->toContain('not-an-image.pdf: This file type is not allowed')
        ->and($result->errors['attachments'][0])->toContain('shell.pdf: This kind of file is not allowed')
        ->and(Subject::query()->where('name', 'Broken')->exists())->toBeFalse()
        ->and(MediaRecord::query()->count())->toBe(0);

    $tooMany = runAction('page:academics-subjects-create', 'create', input: ['name' => 'Many', 'code' => 'MNY', 'attachments' => array_map(static fn (int $i) => fixtureFile('txt', $i.'.txt'), range(1, 4))]);

    expect($tooMany->status)->toBe(ActionStatus::ValidationFailed)
        ->and($tooMany->errors)->toHaveKey('attachments');
});

it('keeps, removes and adds files when editing, and only keeps the record\'s own media', function (): void {
    $subject = $this->world['subjects'][0];
    $a = media()->attach($subject, fixtureFile('txt', 'a.txt'), 'attachments');
    $b = media()->attach($subject, fixtureFile('txt', 'b.txt'), 'attachments');
    $foreign = media()->attach($this->world['subjects'][1], fixtureFile('txt', 'foreign.txt'), 'attachments');

    $forged = runAction('page:academics-subjects-edit', 'edit', $subject->id, ['name' => 'Chemistry', 'code' => 'CHE', 'attachments' => [$a->ulid, $foreign->ulid]]);

    expect($forged->status)->toBe(ActionStatus::ValidationFailed)
        ->and(media()->media($subject, 'attachments')->pluck('ulid')->all())->toBe([$a->ulid, $b->ulid]);

    $edited = runAction('page:academics-subjects-edit', 'edit', $subject->id, ['name' => 'Chemistry', 'code' => 'CHE', 'attachments' => [$b->ulid, fixtureFile('pdf', 'new.pdf')]]);

    expect($edited->status)->toBe(ActionStatus::Success)
        ->and(media()->media($subject->fresh(), 'attachments')->pluck('original_name')->all())->toBe(['b.txt', 'new.pdf'])
        ->and($a->fresh())->not->toBeNull();
});

it('rolls back the record and removes stored files when saving fails', function (): void {
    Subject::created(static fn (): bool => throw new RuntimeException('Downstream failure.'));

    $result = runAction('page:academics-subjects-create', 'create', input: ['name' => 'Doomed', 'code' => 'DMD', 'cover' => fixtureFile('png', 'doomed.png')]);

    expect($result->status)->toBe(ActionStatus::Failed)
        ->and(MediaRecord::query()->count())->toBe(0)
        ->and(app('filesystem')->disk('local')->allFiles('gcego-media'))->toBe([]);
});

it('renders the upload field with current media, limits, progress and remove buttons', function (): void {
    $subject = $this->world['subjects'][0];
    media()->attach($subject, fixtureFile('pdf', 'syllabus.pdf'), 'attachments');
    $html = squish((string) asMember($this->world['admin'])->get('/admin/subjects/'.$subject->id.'/edit')->assertOk()->getContent());

    expect($html)->toContain('Choose files')
        ->toContain('Up to 10 MB per file, 3 at most.')
        ->toContain('role="progressbar"')
        ->toContain('syllabus.pdf')
        ->toContain('<span class="sr-only">Remove syllabus.pdf</span>')
        ->toMatch('/<input id="[^"]+" x-ref="input" type="file" wire:model="data\.attachments" class="sr-only" multiple/')
        ->toContain('accept="image/jpeg,image/png,image/gif,image/webp"');
});
