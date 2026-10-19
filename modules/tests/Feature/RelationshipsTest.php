<?php

declare(strict_types=1);

use Gcego\Actions\Execution\ActionStatus;
use Gcego\Modules\Tests\Fixtures\Models\Subject;
use Gcego\Modules\Tests\Fixtures\Models\Topic;

beforeEach(function (): void {
    $this->world = world();
});

it('lists only the records related to the parent', function (): void {
    $html = squish((string) asMember($this->world['reader'])->get('/admin/subjects/1/topics')->assertOk()->getContent());

    expect($html)->toContain('Organic')->not->toContain('Cells')
        ->and(squish((string) asMember($this->world['reader'])->get('/admin/subjects/2/topics')->assertOk()->getContent()))->not->toContain('Organic');
});

it('lists BelongsToMany relations through the pivot', function (): void {
    $html = squish((string) asMember($this->world['admin'])->get('/admin/subjects/1/tags')->assertOk()->getContent());

    expect($html)->toContain('Core')->not->toContain('Elective')->not->toContain('Foreign');
});

it('refuses parents out of scope or denied, and malformed parent keys', function (string $uri, int $status): void {
    asMember($this->world['admin'])->get($uri)->assertStatus($status);
})->with([
    'other tenant' => ['/admin/subjects/4/topics', 404],
    'missing parent' => ['/admin/subjects/999/topics', 404],
    'policy denies the parent' => ['/admin/subjects/3/topics', 403],
    'malformed key' => ['/admin/subjects/abc/topics', 404],
]);

it('never opens a related record under another parent', function (): void {
    asMember($this->world['admin'])->get('/admin/subjects/1/topics/1')->assertOk()->assertSee('Organic');
    asMember($this->world['admin'])->get('/admin/subjects/2/topics/1')->assertNotFound();
    asMember($this->world['admin'])->get('/admin/subjects/2/topics/1/edit')->assertNotFound();
    asMember($this->world['south'], 2)->get('/admin/subjects/4/topics/1')->assertNotFound();
});

it('checks the related model\'s own policy too', function (): void {
    asMember($this->world['visitor'])->get('/admin/subjects/1/topics')->assertForbidden();
    asMember($this->world['reader'])->get('/admin/subjects/1/topics/create')->assertForbidden();
});

it('creates related records through the form, linked to the parent on the server', function (): void {
    $html = (string) asMember($this->world['editor'])->get('/admin/subjects/2/topics/create')->assertOk()->getContent();
    $snapshot = snapshotOf($html, 'gcego-form');

    livewireUpdate($snapshot, [['method' => 'submit', 'params' => []]], ['data.name' => 'Kinematics'])->assertOk();

    $topic = Topic::query()->where('name', 'Kinematics')->firstOrFail();
    expect($topic->subject_id)->toBe(2)
        ->and($topic->getAttribute('institution_id'))->toBe(1);
});

it('refuses to create under a parent the user may not update', function (): void {
    // Locked: the parent policy denies update (and view).
    asMember($this->world['editor'])->get('/admin/subjects/3/topics/create')->assertForbidden();
    asMember($this->world['editor'])->get('/admin/subjects/4/topics/create')->assertNotFound();
});

it('cannot be pointed at another parent by tampering with the component', function (): void {
    $html = (string) asMember($this->world['editor'])->get('/admin/subjects/2/topics/create')->assertOk()->getContent();
    $snapshot = json_decode(snapshotOf($html, 'gcego-form'), true);
    $snapshot['memo']['path'] = 'admin/subjects/4/topics/create';

    $response = livewireUpdate((string) json_encode($snapshot), [['method' => 'submit', 'params' => []]], ['data.name' => 'Smuggled']);

    expect($response->status())->not->toBe(200)
        ->and(Topic::query()->where('name', 'Smuggled')->exists())->toBeFalse();
});

it('attaches visible records of the same tenant and detaches them', function (): void {
    actAs($this->world['admin']);
    onPage('/admin/subjects/1/tags');

    expect(runAction('page:academics-subjects-tags', 'attach', input: ['record' => '3'])->status)->toBe(ActionStatus::Success)
        ->and(Subject::query()->findOrFail(1)->tags()->pluck('tags.id')->all())->toEqualCanonicalizing([1, 3]);

    $foreign = runAction('page:academics-subjects-tags', 'attach', input: ['record' => '2']);
    expect($foreign->status)->toBe(ActionStatus::ValidationFailed)
        ->and(Subject::query()->findOrFail(1)->tags()->pluck('tags.id')->all())->toEqualCanonicalizing([1, 3]);

    expect(runAction('page:academics-subjects-tags', 'detach', 3)->status)->toBe(ActionStatus::Success)
        ->and(Subject::query()->findOrFail(1)->tags()->pluck('tags.id')->all())->toBe([1]);
});

it('only detaches records related to this parent', function (): void {
    actAs($this->world['admin']);
    onPage('/admin/subjects/2/tags');

    expect(runAction('page:academics-subjects-tags', 'detach', 1)->status)->toBe(ActionStatus::NotFound)
        ->and(Subject::query()->findOrFail(1)->tags()->pluck('tags.id')->all())->toBe([1]);
});

it('requires the parent\'s update ability to change the relationship', function (): void {
    actAs($this->world['reader']);
    onPage('/admin/subjects/1/tags');

    expect(runAction('page:academics-subjects-tags', 'attach', input: ['record' => '3'])->status)->toBe(ActionStatus::Denied)
        ->and(runAction('page:academics-subjects-tags', 'detach', 1)->status)->toBe(ActionStatus::Denied)
        ->and(Subject::query()->findOrFail(1)->tags()->count())->toBe(1);
});

it('does nothing outside a relation page: no parent, no records', function (): void {
    actAs($this->world['admin']);

    expect(runAction('page:academics-subjects-tags', 'detach', 1)->status)->not->toBe(ActionStatus::Success)
        ->and(Subject::query()->findOrFail(1)->tags()->count())->toBe(1);
});

it('links the parent view page to its relationship pages only for permitted users', function (): void {
    $admin = squish((string) asMember($this->world['admin'])->get('/admin/subjects/1')->getContent());

    expect($admin)->toContain('href="/admin/subjects/1/topics"')->toContain('href="/admin/subjects/1/tags"');
});

it('edits related records in place, keeping the parent in links and breadcrumbs', function (): void {
    $view = squish((string) asMember($this->world['editor'])->get('/admin/subjects/1/topics/1')->assertOk()->getContent());
    expect($view)->toContain('href="/admin/subjects/1/topics/1/edit"')->toContain('href="/admin/subjects/1/topics"');

    $html = (string) asMember($this->world['editor'])->get('/admin/subjects/1/topics/1/edit')->assertOk()->getContent();
    livewireUpdate(snapshotOf($html, 'gcego-form'), [['method' => 'submit', 'params' => []]], ['data.name' => 'Organic II'])->assertOk();

    expect(Topic::query()->findOrFail(1)->name)->toBe('Organic II')
        ->and(Topic::query()->findOrFail(1)->subject_id)->toBe(1);
});
