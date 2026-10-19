{{-- gcego-media::library.library — the media library. --}}
@php($presets = \Gcego\Media\Presets\MediaPresets::class)
@php($items = $page->items())
@php($icons = app(\Gcego\Tables\Support\Icons::class))
<div class="{{ implode(' ', $presets::Library) }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="{{ implode(' ', $presets::Title) }}">{{ __('gcego-media::messages.library') }}</h1>
        <div class="flex items-center gap-1" role="group" aria-label="{{ __('gcego-media::messages.layout') }}">
            @foreach (['grid' => 'media.grid-view', 'list' => 'media.list-view'] as $mode => $icon)
                <button type="button" wire:click="$set('layout', '{{ $mode }}')" aria-pressed="{{ $layout === $mode ? 'true' : 'false' }}" class="{{ implode(' ', $presets::Remove) }} {{ $layout === $mode ? 'bg-gcego-surface-muted text-gcego-text' : '' }}">
                    {{ $icons->svg($icon, 'size-5') }}<span class="sr-only">{{ __('gcego-media::messages.layouts.'.$mode) }}</span>
                </button>
            @endforeach
        </div>
    </div>

    @if ($canUpload)
        @include('gcego-media::partials.upload-input', ['property' => 'files', 'id' => 'gcego-media-library-'.$this->getId(), 'rules' => app(\Gcego\Media\Uploads\UploadManager::class)->rules(new \Gcego\Media\Uploads\UploadContext($uploadCollection)), 'messages' => $uploadErrors])
        @if ($files !== [])
            <button type="button" wire:click="upload" wire:loading.attr="disabled" class="{{ implode(' ', $presets::Button) }} self-start">{{ trans_choice('gcego-media::messages.upload_count', count($files), ['count' => count($files)]) }}</button>
        @endif
    @endif

    <form class="{{ implode(' ', $presets::Toolbar) }}" wire:submit.prevent role="search" aria-label="{{ __('gcego-media::messages.filters') }}">
        <label class="{{ implode(' ', $presets::Field) }}">{{ __('gcego-media::messages.search') }}
            <input type="search" wire:model.live.debounce.400ms="search" maxlength="100" class="{{ implode(' ', $presets::Control) }}">
        </label>
        <label class="{{ implode(' ', $presets::Field) }}">{{ __('gcego-media::messages.type') }}
            <select wire:model.live="kind" class="{{ implode(' ', $presets::Control) }}">
                <option value="">{{ __('gcego-media::messages.all_types') }}</option>
                @foreach (\Gcego\Media\Library\MediaQuery::Kinds as $kindOption)
                    <option value="{{ $kindOption }}">{{ __('gcego-media::messages.kinds.'.$kindOption) }}</option>
                @endforeach
            </select>
        </label>
        @if ($collections !== [])
            <label class="{{ implode(' ', $presets::Field) }}">{{ __('gcego-media::messages.collection') }}
                <select wire:model.live="collection" class="{{ implode(' ', $presets::Control) }}">
                    <option value="">{{ __('gcego-media::messages.all_collections') }}</option>
                    @foreach ($collections as $name)
                        <option value="{{ $name }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="{{ implode(' ', $presets::Field) }}">{{ __('gcego-media::messages.sort') }}
            <select wire:model.live="sort" class="{{ implode(' ', $presets::Control) }}">
                @foreach (\Gcego\Media\Library\MediaQuery::Sorts as $sortOption)
                    <option value="{{ $sortOption }}">{{ __('gcego-media::messages.sorts.'.$sortOption) }}</option>
                @endforeach
            </select>
        </label>
        <label class="{{ implode(' ', $presets::Field) }}">{{ __('gcego-media::messages.direction') }}
            <select wire:model.live="direction" class="{{ implode(' ', $presets::Control) }}">
                <option value="desc">{{ __('gcego-media::messages.descending') }}</option>
                <option value="asc">{{ __('gcego-media::messages.ascending') }}</option>
            </select>
        </label>
    </form>

    @if ($selected !== [])
        <div class="{{ implode(' ', $presets::Toolbar) }}" role="group" aria-label="{{ __('gcego-media::messages.bulk') }}">
            <span class="text-sm text-gcego-text-muted" role="status">{{ trans_choice('gcego-media::messages.selected', count($selected), ['count' => count($selected)]) }}</span>
            <button type="button" wire:click="delete" wire:confirm="{{ __('gcego-media::messages.delete_confirm') }}" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-media::messages.delete') }}</button>
        </div>
    @endif

    <div wire:loading.class="opacity-60" class="flex flex-col gap-4 lg:flex-row">
        <div class="min-w-0 flex-1">
            @if ($items === [])
                <div class="{{ implode(' ', $presets::Empty) }}">
                    {{ $icons->svg('media.image', 'size-8 text-gcego-text-muted') }}
                    <p class="text-sm text-gcego-text-muted">{{ __('gcego-media::messages.empty') }}</p>
                </div>
            @elseif ($layout === 'grid')
                <ul role="list" class="{{ implode(' ', $presets::Grid) }}">
                    @foreach ($items as $media)
                        <li wire:key="media-{{ $media->ulid }}" class="{{ implode(' ', $presets::Card) }} {{ in_array($media->ulid, $selected, true) ? implode(' ', $presets::CardSelected) : '' }}">
                            <input type="checkbox" wire:model.live="selected" value="{{ $media->ulid }}" aria-label="{{ __('gcego-media::messages.select', ['name' => $media->original_name]) }}" class="{{ implode(' ', $presets::CardCheckbox) }}">
                            <button type="button" wire:click="show('{{ $media->ulid }}')" class="{{ implode(' ', $presets::CardPreview) }} focus-visible:outline-2 focus-visible:outline-gcego-ring" aria-label="{{ __('gcego-media::messages.details_for', ['name' => $media->original_name]) }}">
                                @if ($media->isImage() && $access->available($media))
                                    <img src="{{ $manager->url($media, $media->variant('thumb') !== null ? 'thumb' : null) }}" alt="{{ $media->alt ?? '' }}" loading="lazy" decoding="async" class="{{ implode(' ', $presets::CardImage) }}">
                                @else
                                    {{ $icons->svg(\Gcego\Media\Support\FileIcon::for($media->kind()), 'size-10 text-gcego-text-muted') }}
                                @endif
                            </button>
                            <div class="{{ implode(' ', $presets::CardBody) }}">
                                <span class="truncate text-sm font-medium text-gcego-text">{{ $media->original_name }}</span>
                                <span class="{{ implode(' ', $presets::FileMeta) }}">{{ strtoupper($media->extension) }} · {{ \Gcego\Media\Support\Bytes::format($media->size) }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="overflow-x-auto rounded-gcego-lg border border-gcego-border bg-gcego-surface">
                    <table class="{{ implode(' ', $presets::Table) }}">
                        <caption class="sr-only">{{ __('gcego-media::messages.library') }}</caption>
                        <thead><tr class="border-b border-gcego-border">
                            <th scope="col" class="{{ implode(' ', $presets::Cell) }}"><span class="sr-only">{{ __('gcego-media::messages.select_column') }}</span></th>
                            <th scope="col" class="{{ implode(' ', $presets::Cell) }}">{{ __('gcego-media::messages.name') }}</th>
                            <th scope="col" class="{{ implode(' ', $presets::Cell) }}">{{ __('gcego-media::messages.type') }}</th>
                            <th scope="col" class="{{ implode(' ', $presets::Cell) }}">{{ __('gcego-media::messages.size') }}</th>
                            <th scope="col" class="{{ implode(' ', $presets::Cell) }}">{{ __('gcego-media::messages.uploaded_at') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gcego-border">
                            @foreach ($items as $media)
                                <tr wire:key="row-{{ $media->ulid }}">
                                    <td class="{{ implode(' ', $presets::Cell) }}"><input type="checkbox" wire:model.live="selected" value="{{ $media->ulid }}" aria-label="{{ __('gcego-media::messages.select', ['name' => $media->original_name]) }}" class="size-4"></td>
                                    <td class="{{ implode(' ', $presets::Cell) }}"><button type="button" wire:click="show('{{ $media->ulid }}')" class="{{ implode(' ', $presets::Link) }}">{{ $media->original_name }}</button></td>
                                    <td class="{{ implode(' ', $presets::Cell) }} text-gcego-text-muted">{{ $media->mime }}</td>
                                    <td class="{{ implode(' ', $presets::Cell) }} tabular-nums">{{ \Gcego\Media\Support\Bytes::format($media->size) }}</td>
                                    <td class="{{ implode(' ', $presets::Cell) }}"><time datetime="{{ $media->created_at?->toIso8601String() }}">{{ $media->created_at?->isoFormat('LL') }}</time></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($page->hasPages())
                <nav aria-label="{{ __('gcego-media::messages.pagination') }}" class="mt-4 flex items-center justify-between gap-3 text-sm">
                    <button type="button" wire:click="previousPage" @disabled($page->onFirstPage()) class="{{ implode(' ', $presets::Link) }} disabled:opacity-50">{{ __('gcego-media::messages.previous') }}</button>
                    <span class="text-gcego-text-muted">{{ __('gcego-media::messages.page', ['page' => $page->currentPage(), 'pages' => $page->lastPage()]) }}</span>
                    <button type="button" wire:click="nextPage" @disabled(! $page->hasMorePages()) class="{{ implode(' ', $presets::Link) }} disabled:opacity-50">{{ __('gcego-media::messages.next') }}</button>
                </nav>
            @endif
        </div>

        @if ($detail !== null)
            <aside aria-label="{{ __('gcego-media::messages.details') }}" class="{{ implode(' ', $presets::Details) }} lg:w-80 lg:shrink-0" wire:key="details-{{ $detail->ulid }}" x-data x-init="$el.focus()" tabindex="-1">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="min-w-0 break-words text-sm font-semibold text-gcego-text">{{ $detail->original_name }}</h2>
                    <button type="button" wire:click="show(null)" class="{{ implode(' ', $presets::Remove) }}">{{ $icons->svg('media.close', 'size-5') }}<span class="sr-only">{{ __('gcego-media::messages.close') }}</span></button>
                </div>
                @if ($detail->isImage() && $access->available($detail))
                    <img src="{{ $manager->url($detail) }}" alt="{{ $detail->alt ?? '' }}" class="{{ implode(' ', $presets::Preview) }} mt-3">
                @endif
                <dl class="{{ implode(' ', $presets::DetailsList) }} mt-3">
                    <div><dt class="{{ implode(' ', $presets::Term) }}">{{ __('gcego-media::messages.type') }}</dt><dd>{{ $detail->mime }}</dd></div>
                    <div><dt class="{{ implode(' ', $presets::Term) }}">{{ __('gcego-media::messages.size') }}</dt><dd>{{ \Gcego\Media\Support\Bytes::format($detail->size) }}</dd></div>
                    @if (is_int($detail->meta('width')))
                        <div><dt class="{{ implode(' ', $presets::Term) }}">{{ __('gcego-media::messages.dimensions') }}</dt><dd>{{ $detail->meta('width') }} × {{ $detail->meta('height') }}</dd></div>
                    @endif
                    @if (is_numeric($detail->meta('duration')))
                        <div><dt class="{{ implode(' ', $presets::Term) }}">{{ __('gcego-media::messages.duration') }}</dt><dd>{{ gmdate('H:i:s', (int) round((float) $detail->meta('duration'))) }}</dd></div>
                    @endif
                    <div><dt class="{{ implode(' ', $presets::Term) }}">{{ __('gcego-media::messages.collection') }}</dt><dd>{{ $detail->collection }}</dd></div>
                    <div><dt class="{{ implode(' ', $presets::Term) }}">{{ __('gcego-media::messages.uploaded_at') }}</dt><dd><time datetime="{{ $detail->created_at?->toIso8601String() }}">{{ $detail->created_at?->isoFormat('LLL') }}</time></dd></div>
                    <div><dt class="{{ implode(' ', $presets::Term) }}">{{ __('gcego-media::messages.status') }}</dt><dd>{{ __('gcego-media::messages.statuses.'.$detail->status->value) }}</dd></div>
                </dl>
                @if ($access->allows(\Gcego\Media\Definitions\MediaAbility::Download, $detail))
                    <a href="{{ $manager->url($detail, download: true) }}" class="{{ implode(' ', $presets::Button) }} mt-4">{{ $icons->svg('media.download', 'size-5') }} {{ __('gcego-media::messages.download') }}</a>
                @endif
            </aside>
        @endif
    </div>
</div>
