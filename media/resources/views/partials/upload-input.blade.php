{{-- gcego-media::partials.upload-input — a labelled file input with an optional drop zone and real Livewire progress. --}}
@php($presets = \Gcego\Media\Presets\MediaPresets::class)
<div class="flex flex-col gap-2" x-data="{ uploading: false, progress: 0, dragging: false, failed: false }"
    x-on:livewire-upload-start="uploading = true; failed = false; progress = 0" x-on:livewire-upload-finish="uploading = false"
    x-on:livewire-upload-cancel="uploading = false" x-on:livewire-upload-error="uploading = false; failed = true"
    x-on:livewire-upload-progress="progress = $event.detail.progress">
    <div class="{{ implode(' ', $presets::Dropzone) }}" x-bind:class="dragging && @js(implode(' ', $presets::DropzoneActive))"
        x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="dragging = false; if ($event.dataTransfer.files.length) { $refs.input.files = $event.dataTransfer.files; $refs.input.dispatchEvent(new Event('change', { bubbles: true })) }">
        <label class="{{ implode(' ', $presets::Button) }}">
            <span>{{ __('gcego-media::messages.choose_files') }}</span>
            <input x-ref="input" type="file" wire:model="{{ $property }}" multiple class="sr-only" aria-describedby="{{ $id }}-limits" @if ($rules->types !== []) accept="{{ implode(',', $rules->types) }}" @endif>
        </label>
        <p class="{{ implode(' ', $presets::Hint) }}">{{ __('gcego-media::messages.or_drop') }}</p>
        <p id="{{ $id }}-limits" class="{{ implode(' ', $presets::Hint) }}">{{ __('gcego-media::messages.limits', ['size' => \Gcego\Media\Support\Bytes::format($rules->maxSize * 1024), 'count' => $rules->single ? 1 : $rules->maxFiles]) }}</p>
    </div>
    <div x-show="uploading" x-cloak class="flex items-center gap-3">
        <div role="progressbar" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="progress" aria-label="{{ __('gcego-media::messages.uploading') }}" class="{{ implode(' ', $presets::Progress) }}">
            <div class="{{ implode(' ', $presets::ProgressBar) }}" x-bind:style="'width: ' + progress + '%'"></div>
        </div>
        <span class="text-xs tabular-nums text-gcego-text-muted" aria-hidden="true" x-text="progress + '%'"></span>
        <button type="button" x-on:click="$wire.cancelUpload(@js($property))" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-media::messages.cancel') }}</button>
    </div>
    <p x-show="failed" x-cloak role="alert" class="{{ implode(' ', $presets::Error) }}">{{ __('gcego-media::messages.upload_failed') }}</p>
    <span class="sr-only" role="status" aria-live="polite" x-text="uploading ? @js(__('gcego-media::messages.uploading')) : ''"></span>
    @if ($messages !== [])
        <ul role="alert" class="{{ implode(' ', $presets::Error) }}">
            @foreach ($messages as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
</div>
