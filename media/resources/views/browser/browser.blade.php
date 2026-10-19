{{-- gcego-media::browser.browser — choose existing media to attach. --}}
@php($presets = \Gcego\Media\Presets\MediaPresets::class)
@php($icons = app(\Gcego\Tables\Support\Icons::class))
<div class="flex flex-col gap-4">
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
    </form>
    <fieldset>
        <legend class="sr-only">{{ __('gcego-media::messages.choose_media') }}</legend>
        @if ($page->items() === [])
            <p class="text-sm text-gcego-text-muted">{{ __('gcego-media::messages.empty') }}</p>
        @else
            <ul role="list" class="{{ implode(' ', $presets::Grid) }}">
                @foreach ($page->items() as $media)
                    <li wire:key="browse-{{ $media->ulid }}">
                        <label class="{{ implode(' ', $presets::Card) }} cursor-pointer focus-within:outline-2 focus-within:outline-gcego-ring {{ in_array($media->ulid, $selected, true) ? implode(' ', $presets::CardSelected) : '' }}">
                            <input type="checkbox" wire:model.live="selected" value="{{ $media->ulid }}" class="{{ implode(' ', $presets::CardCheckbox) }}">
                            <span class="{{ implode(' ', $presets::CardPreview) }}">
                                @if ($media->isImage())
                                    <img src="{{ $manager->url($media, $media->variant('thumb') !== null ? 'thumb' : null) }}" alt="" loading="lazy" class="{{ implode(' ', $presets::CardImage) }}">
                                @else
                                    {{ $icons->svg(\Gcego\Media\Support\FileIcon::for($media->kind()), 'size-10 text-gcego-text-muted') }}
                                @endif
                            </span>
                            <span class="{{ implode(' ', $presets::CardBody) }}"><span class="truncate text-sm font-medium text-gcego-text">{{ $media->original_name }}</span><span class="{{ implode(' ', $presets::FileMeta) }}">{{ \Gcego\Media\Support\Bytes::format($media->size) }}</span></span>
                        </label>
                    </li>
                @endforeach
            </ul>
        @endif
    </fieldset>
    @error('selected')<p role="alert" class="{{ implode(' ', $presets::Error) }}">{{ $message }}</p>@enderror
    @if ($canAttach)
        <button type="button" wire:click="attach" @disabled($selected === []) class="{{ implode(' ', $presets::Button) }} self-start disabled:opacity-50">{{ trans_choice('gcego-media::messages.attach_count', count($selected), ['count' => count($selected)]) }}</button>
    @endif
    @if ($page->hasPages())
        <nav aria-label="{{ __('gcego-media::messages.pagination') }}" class="flex items-center justify-between gap-3 text-sm">
            <button type="button" wire:click="previousPage" @disabled($page->onFirstPage()) class="{{ implode(' ', $presets::Link) }} disabled:opacity-50">{{ __('gcego-media::messages.previous') }}</button>
            <button type="button" wire:click="nextPage" @disabled(! $page->hasMorePages()) class="{{ implode(' ', $presets::Link) }} disabled:opacity-50">{{ __('gcego-media::messages.next') }}</button>
        </nav>
    @endif
</div>
