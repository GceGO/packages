<?php

declare(strict_types=1);

namespace Gcego\Media\Livewire;

use Gcego\Media\Access\MediaAccess;
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\Library\MediaLibraryQuery;
use Gcego\Media\Library\MediaQuery;
use Gcego\Media\MediaManager;
use Gcego\Media\Support\Keys;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * <livewire:gcego-media-browser :owner="$lesson" collection="lesson-resources" />:
 * pick existing library media and attach it to a record. The record and
 * collection are fixed when the component mounts (#[Locked]; Livewire's
 * checksum stops changes), selections are re-checked on the server, and
 * attaching needs the record's "update" ability.
 */
class MediaBrowser extends Component
{
    use WithPagination;

    #[Locked]
    public ?string $ownerType = null;

    #[Locked]
    public ?string $ownerKey = null;

    #[Locked]
    public string $collection = 'default';

    public string $search = '';

    public string $kind = '';

    /** @var list<string> */
    public array $selected = [];

    public function mount(?Model $owner = null, string $collection = 'default'): void
    {
        $this->ownerType = $owner?->getMorphClass();
        $this->ownerKey = $owner === null ? null : Keys::string($owner->getKey());
        $this->collection = $collection;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'kind'], true)) {
            $this->resetPage();
        }
    }

    public function attach(): void
    {
        $owner = $this->owner();
        $access = app(MediaAccess::class);
        $attached = 0;

        if ($owner === null || ! $access->allows(MediaAbility::Attach, null, $owner)) {
            $this->addError('selected', (string) __('gcego-media::messages.errors.denied'));

            return;
        }

        foreach (array_slice(array_values(array_filter($this->selected, is_string(...))), 0, 50) as $ulid) {
            try {
                $media = $access->find($ulid);
                app(MediaManager::class)->attach($owner, $media, $this->collection);
                $attached++;
            } catch (Throwable) {
                continue;
            }
        }

        $this->selected = [];
        $this->dispatch('gcego-media-attached', collection: $this->collection, count: $attached);
    }

    public function render(): View
    {
        $filters = MediaQuery::fromInput($this->search, null, $this->kind);

        return view('gcego-media::browser.browser', [
            'page' => app(MediaLibraryQuery::class)->page($filters, is_int($number = $this->getPage()) ? $number : 1, 12),
            'manager' => app(MediaManager::class),
            'canAttach' => ($owner = $this->owner()) !== null && app(MediaAccess::class)->allows(MediaAbility::Attach, null, $owner),
        ]);
    }

    private function owner(): ?Model
    {
        if ($this->ownerType === null || $this->ownerKey === null) {
            return null;
        }

        $class = Relation::getMorphedModel($this->ownerType) ?? $this->ownerType;

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class::query()->find($this->ownerKey);
    }
}
