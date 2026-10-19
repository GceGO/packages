<?php

declare(strict_types=1);

namespace Gcego\Media\Livewire;

use Gcego\Media\Access\MediaAccess;
use Gcego\Media\Collections\MediaCollectionRegistry;
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\Library\MediaLibraryQuery;
use Gcego\Media\Library\MediaQuery;
use Gcego\Media\MediaManager;
use Gcego\Media\Uploads\UploadContext;
use Gcego\Media\Uploads\UploadManager;
use Gcego\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

/**
 * <livewire:gcego-media-library />: the viewer's media (their uploads, or
 * the tenant's for media managers) as a grid or a list, with search,
 * filters, sorting, pagination, details, downloads, uploads into the
 * library collection and bulk deletion. Every id the browser sends is
 * looked up in the viewer's tenant and authorized before anything happens.
 */
class MediaLibrary extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $collection = '';

    #[Url(except: '')]
    public string $kind = '';

    #[Url(except: 'created_at')]
    public string $sort = 'created_at';

    #[Url(except: 'desc')]
    public string $direction = 'desc';

    #[Url(except: 'grid')]
    public string $layout = 'grid';

    /** @var list<string> */
    public array $selected = [];

    public ?string $details = null;

    /** @var list<UploadedFile> */
    public array $files = [];

    /** @var list<string> */
    public array $uploadErrors = [];

    #[Locked]
    public string $uploadCollection = 'library';

    public function mount(string $collection = 'library'): void
    {
        $this->uploadCollection = app(MediaCollectionRegistry::class)->get($collection)->collectionName();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'collection', 'kind', 'sort', 'direction'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function upload(): void
    {
        $this->uploadErrors = [];
        $results = app(UploadManager::class)->uploadMany(array_values(array_filter($this->files, static fn (mixed $file): bool => $file instanceof UploadedFile)), new UploadContext($this->uploadCollection));
        $stored = 0;

        foreach ($results as $result) {
            if ($result->successful()) {
                $stored++;
            } else {
                $this->uploadErrors = [...$this->uploadErrors, ...array_map(static fn (string $error): string => $result->name.': '.$error, $result->errors)];
            }
        }

        $this->files = [];
        $this->resetPage();

        if ($stored > 0) {
            $this->toast(trans_choice('gcego-media::messages.uploaded', $stored, ['count' => $stored]));
        }
    }

    public function show(mixed $ulid): void
    {
        $this->details = is_string($ulid) && $this->visible($ulid) ? strtolower($ulid) : null;
    }

    public function delete(): void
    {
        $access = app(MediaAccess::class);
        $deleted = 0;

        foreach (array_slice(array_values(array_filter($this->selected, is_string(...))), 0, 100) as $ulid) {
            try {
                $media = $access->find($ulid);
            } catch (Throwable) {
                continue;
            }

            if ($access->allows(MediaAbility::Delete, $media) && app(MediaManager::class)->delete($media)) {
                $deleted++;
            }
        }

        $this->selected = [];
        $this->details = null;

        if ($deleted > 0) {
            $this->toast(trans_choice('gcego-media::messages.deleted', $deleted, ['count' => $deleted]));
        }
    }

    public function render(): View
    {
        $filters = MediaQuery::fromInput($this->search, $this->collection, $this->kind, null, null, null, $this->sort, $this->direction);
        $access = app(MediaAccess::class);
        $page = app(MediaLibraryQuery::class)->page($filters, is_int($number = $this->getPage()) ? $number : 1);
        $details = $this->details === null ? null : collect($page->items())->firstWhere('ulid', $this->details) ?? ($this->visible($this->details) ? $access->find($this->details) : null);

        return view('gcego-media::library.library', [
            'page' => $page,
            'detail' => $details,
            'manager' => app(MediaManager::class),
            'access' => $access,
            'collections' => array_keys(app(MediaCollectionRegistry::class)->all()),
            'canUpload' => $access->allows(MediaAbility::Upload),
        ]);
    }

    private function visible(string $ulid): bool
    {
        $filters = new MediaQuery;

        return app(MediaLibraryQuery::class)->query($filters)->where('ulid', strtolower($ulid))->exists();
    }

    private function toast(string $message): void
    {
        if (class_exists(Notification::class)) {
            Notification::success($message);
        }
    }
}
