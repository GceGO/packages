<?php

declare(strict_types=1);

namespace Gcego\Media\Livewire;

use Gcego\Media\Collections\MediaCollectionRegistry;
use Gcego\Media\MediaManager;
use Gcego\Media\Support\Keys;
use Gcego\Media\Uploads\UploadContext;
use Gcego\Media\Uploads\UploadManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * <livewire:gcego-media-uploader collection="lesson-resources" :owner="$lesson" />:
 * a standalone uploader (outside forms) with progress, per-file results
 * and the owner's current media. The owner and collection are locked at
 * mount; uploads are validated by content, bound to their uploader and
 * authorized ("attach" on the owner).
 */
class MediaUploader extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $collection = 'default';

    #[Locked]
    public ?string $ownerType = null;

    #[Locked]
    public ?string $ownerKey = null;

    /** @var list<UploadedFile> */
    public array $files = [];

    /** @var list<string> */
    public array $uploadErrors = [];

    public int $stored = 0;

    public function mount(string $collection = 'default', ?Model $owner = null): void
    {
        $this->collection = app(MediaCollectionRegistry::class)->get($collection)->collectionName();
        $this->ownerType = $owner?->getMorphClass();
        $this->ownerKey = $owner === null ? null : Keys::string($owner->getKey());
    }

    public function save(): void
    {
        $this->uploadErrors = [];
        $files = array_values(array_filter($this->files, static fn (mixed $file): bool => $file instanceof UploadedFile));
        $results = app(MediaManager::class)->uploadMany($files, $this->collection, $this->owner());
        $this->stored = 0;

        foreach ($results as $result) {
            if ($result->successful()) {
                $this->stored++;
            } else {
                $this->uploadErrors = [...$this->uploadErrors, ...array_map(static fn (string $error): string => $result->name.': '.$error, $result->errors)];
            }
        }

        $this->files = [];
        $this->dispatch('gcego-media-uploaded', collection: $this->collection, count: $this->stored);
    }

    public function render(): View
    {
        $owner = $this->owner();
        $rules = app(UploadManager::class)->rules(new UploadContext($this->collection, $owner));

        return view('gcego-media::uploads.uploader', [
            'rules' => $rules,
            'current' => $owner === null ? collect() : app(MediaManager::class)->media($owner, $this->collection),
            'manager' => app(MediaManager::class),
        ]);
    }

    private function owner(): ?Model
    {
        if ($this->ownerType === null || $this->ownerKey === null) {
            return null;
        }

        $class = Relation::getMorphedModel($this->ownerType) ?? $this->ownerType;

        return is_string($class) && is_subclass_of($class, Model::class) ? $class::query()->find($this->ownerKey) : null;
    }
}
