<?php

declare(strict_types=1);

use Gcego\Media\Livewire\MediaBrowser;
use Gcego\Media\Livewire\MediaLibrary;
use Gcego\Media\Livewire\MediaUploader;
use Gcego\Media\Models\MediaRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['admin']);
});

it('serves the library inside each panel, behind the panel checks', function (): void {
    media()->upload(fixtureFile('png', 'diagram.png'), 'library');
    $html = html(asMember($this->world['admin'])->get('/admin/media')->assertOk());

    expect(route('gcego.admin.media', [], false))->toBe('/admin/media')
        ->and($html)->toContain('data-gcego-panel="admin"')->toContain('Media library')->toContain('diagram.png')
        ->and(asMember($this->world['editor'])->get('/admin/media')->getStatusCode())->toBe(403);
});

it('uploads into the library with real Livewire uploads, per file', function (): void {
    $component = Livewire::test(MediaLibrary::class)
        ->set('files', [UploadedFile::fake()->image('first.png', 50, 50), UploadedFile::fake()->createWithContent('evil.png', '<?php system($_GET["c"]);')])
        ->call('upload');

    expect(MediaRecord::query()->pluck('original_name')->all())->toBe(['first.png'])
        ->and(MediaRecord::query()->value('collection'))->toBe('library')
        ->and($component->get('uploadErrors'))->toHaveCount(1)
        ->and($component->get('uploadErrors')[0])->toContain('evil.png')
        ->and($component->get('files'))->toBe([]);
});

it('lists the viewer\'s own media, searches, filters, sorts and paginates on the server', function (): void {
    foreach (['alpha.png' => 'png', 'beta.pdf' => 'pdf', 'gamma.txt' => 'txt'] as $name => $kind) {
        $this->travel(1)->minutes();
        media()->upload(fixtureFile($kind, $name), 'library');
    }

    actAs($this->world['editor']);
    media()->upload(fixtureFile('txt', 'editors.txt'), 'library');
    actAs($this->world['admin']);

    $component = Livewire::test(MediaLibrary::class);

    expect(array_map(static fn ($media) => $media->original_name, $component->viewData('page')->items()))->toBe(['gamma.txt', 'beta.pdf', 'alpha.png'])
        ->and(array_map(static fn ($media) => $media->original_name, $component->set('search', 'bet')->viewData('page')->items()))->toBe(['beta.pdf'])
        ->and(array_map(static fn ($media) => $media->original_name, $component->set('search', '')->set('kind', 'image')->viewData('page')->items()))->toBe(['alpha.png'])
        ->and(array_map(static fn ($media) => $media->original_name, $component->set('kind', '')->set('sort', 'original_name')->set('direction', 'asc')->viewData('page')->items()))->toBe(['alpha.png', 'beta.pdf', 'gamma.txt'])
        ->and(array_map(static fn ($media) => $media->original_name, $component->set('sort', 'DROP TABLE')->viewData('page')->items()))->toHaveCount(3)
        ->and(array_map(static fn ($media) => $media->original_name, $component->set('search', "%' OR 1=1 --")->viewData('page')->items()))->toBe([]);

    Gate::define('gcego-media.manage', static fn ($user): bool => $user->name === 'Admin');

    expect(Livewire::test(MediaLibrary::class)->viewData('page')->total())->toBe(4);
});

it('shows details and deletes the selection it may delete', function (): void {
    $mine = media()->upload(fixtureFile('png', 'mine.png', 320, 200), 'library');
    actAs($this->world['editor']);
    $theirs = media()->upload(fixtureFile('txt', 'theirs.txt'), 'library');
    actAs($this->world['admin']);

    $component = Livewire::test(MediaLibrary::class)->call('show', $mine->ulid);

    expect($component->viewData('detail')?->ulid)->toBe($mine->ulid)
        ->and(squish($component->html()))->toContain('320 × 200')
        ->and($component->call('show', $theirs->ulid)->viewData('detail'))->toBeNull();

    $component->set('selected', [$mine->ulid, $theirs->ulid, 'not-an-id'])->call('delete');

    expect(MediaRecord::query()->find($mine->getKey()))->toBeNull()
        ->and(MediaRecord::query()->find($theirs->getKey()))->not->toBeNull();
});

it('attaches chosen library media to a record, only with update rights', function (): void {
    $file = media()->upload(fixtureFile('pdf', 'handout.pdf'), 'library');
    $subject = $this->world['subjects'][0];

    Livewire::test(MediaBrowser::class, ['owner' => $subject, 'collection' => 'attachments'])
        ->set('selected', [$file->ulid])
        ->call('attach')
        ->assertDispatched('gcego-media-attached');

    expect(media()->media($subject, 'attachments')->pluck('ulid')->all())->toBe([$file->ulid]);

    actAs($this->world['reader']);
    Livewire::test(MediaBrowser::class, ['owner' => $this->world['subjects'][1], 'collection' => 'attachments'])
        ->set('selected', [$file->ulid])
        ->call('attach')
        ->assertHasErrors('selected');

    expect(media()->media($this->world['subjects'][1], 'attachments'))->toHaveCount(0);
});

it('keeps the browser\'s record and collection out of the browser\'s reach', function (): void {
    $component = Livewire::test(MediaBrowser::class, ['owner' => $this->world['subjects'][0], 'collection' => 'attachments']);

    expect(fn () => $component->set('ownerKey', (string) $this->world['subjects'][3]->id))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => $component->set('collection', 'cover'))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('uploads and attaches with the standalone uploader', function (): void {
    $subject = $this->world['subjects'][0];

    Livewire::test(MediaUploader::class, ['collection' => 'attachments', 'owner' => $subject])
        ->set('files', [UploadedFile::fake()->createWithContent('notes.txt', "Plain notes\n")])
        ->call('save')
        ->assertSet('stored', 1)
        ->assertDispatched('gcego-media-uploaded')
        ->assertSee('notes.txt');

    expect(media()->media($subject, 'attachments')->pluck('original_name')->all())->toBe(['notes.txt']);
});

it('labels uploads, progress and selection for keyboard and screen-reader users', function (): void {
    media()->upload(fixtureFile('png', 'labelled.png'), 'library');
    $html = squish(Livewire::test(MediaLibrary::class)->html());

    expect($html)->toContain('<label class="inline-flex')
        ->toContain('type="file" wire:model="files" multiple class="sr-only"')
        ->toContain('role="progressbar"')
        ->toContain('role="status" aria-live="polite"')
        ->toContain('aria-label="Select “labelled.png”"')
        ->toContain('aria-label="Details of “labelled.png”"')
        ->toContain('role="search" aria-label="Filter media"')
        ->toContain('aria-pressed="true"');
});
