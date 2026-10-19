{{-- gcego-media::uploads.field — the MediaUpload field: a labelled file input (keyboard and screen readers), an optional
     drop zone on top of it, real Livewire upload progress, current media and new uploads with remove buttons. File
     names and messages are escaped. --}}
@php($presets = \Gcego\Media\Presets\MediaPresets::class)
@php($rules = $field->rules())
@php($items = is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]))
@php($existing = app(\Gcego\Media\Access\MediaAccess::class)->query()->whereIn('ulid', array_values(array_filter($items, 'is_string')))->get()->keyBy('ulid'))
@php($media = app(\Gcego\Media\MediaManager::class))
<div class="{{ $styles('root') }} {{ $span }}" data-gcego-field="{{ $path }}"
    x-data="{ uploading: false, progress: 0, dragging: false, failed: false }"
    x-on:livewire-upload-start="uploading = true; failed = false; progress = 0"
    x-on:livewire-upload-finish="uploading = false"
    x-on:livewire-upload-cancel="uploading = false"
    x-on:livewire-upload-error="uploading = false; failed = true"
    x-on:livewire-upload-progress="progress = $event.detail.progress">
    @include('gcego-forms::fields.partials.label')
    <div class="{{ implode(' ', $presets::Dropzone) }}" x-bind:class="dragging && @js(implode(' ', $presets::DropzoneActive))"
        x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="dragging = false; if ($event.dataTransfer.files.length) { $refs.input.files = $event.dataTransfer.files; $refs.input.dispatchEvent(new Event('change', { bubbles: true })) }">
        <label class="{{ implode(' ', $presets::Button) }}">
            <span>{{ $field->isMultiple() ? __('gcego-media::messages.choose_files') : __('gcego-media::messages.choose_file') }}</span>
            <input id="{{ $id }}" x-ref="input" type="file" {{ $binding }} class="sr-only"
                @if ($field->isMultiple()) multiple @endif
                @if ($rules->types !== []) accept="{{ implode(',', $rules->types) }}" @endif
                @disabled($disabled)
                @include('gcego-forms::fields.partials.aria')>
        </label>
        <p class="{{ implode(' ', $presets::Hint) }}">{{ __('gcego-media::messages.or_drop') }}</p>
        <p class="{{ implode(' ', $presets::Hint) }}" id="{{ $id }}-limits">
            {{ __('gcego-media::messages.limits', ['size' => \Gcego\Media\Support\Bytes::format($rules->maxSize * 1024), 'count' => $rules->single ? 1 : $rules->maxFiles]) }}
        </p>
    </div>
    <div x-show="uploading" x-cloak class="flex items-center gap-3">
        <div role="progressbar" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="progress" aria-label="{{ __('gcego-media::messages.uploading') }}" class="{{ implode(' ', $presets::Progress) }}">
            <div class="{{ implode(' ', $presets::ProgressBar) }}" x-bind:style="'width: ' + progress + '%'"></div>
        </div>
        <span class="text-xs tabular-nums text-gcego-text-muted" aria-hidden="true" x-text="progress + '%'"></span>
        <button type="button" x-on:click="$wire.cancelUpload(@js($model))" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-media::messages.cancel') }}</button>
    </div>
    <p x-show="failed" x-cloak role="alert" class="{{ implode(' ', $presets::Error) }}">{{ __('gcego-media::messages.upload_failed') }}</p>
    <span class="sr-only" role="status" aria-live="polite" x-text="uploading ? @js(__('gcego-media::messages.uploading')) : ''"></span>
    @if ($items !== [])
        <ul role="list" class="{{ implode(' ', $presets::FileList) }}">
            @foreach ($items as $index => $item)
                @php($slot = $field->isMultiple() ? $model.'.'.$index : $model)
                <li class="{{ implode(' ', $presets::FileItem) }}" wire:key="{{ $id }}-item-{{ is_string($item) ? $item : $index }}">
                    @if (is_string($item) && $existing->has($item))
                        @php($record = $existing->get($item))
                        @include('gcego-media::partials.thumbnail', ['media' => $record, 'manager' => $media])
                        <span class="{{ implode(' ', $presets::FileName) }}">{{ $record->original_name }}<span class="{{ implode(' ', $presets::FileMeta) }}">{{ \Gcego\Media\Support\Bytes::format($record->size) }}</span></span>
                        @php($label = $record->original_name)
                    @elseif ($item instanceof \Illuminate\Http\UploadedFile)
                        @if (method_exists($item, 'isPreviewable') && $item->isPreviewable() && in_array($item->getMimeType(), \Gcego\Media\Media::ImageTypes, true))
                            <img src="{{ $item->temporaryUrl() }}" alt="" class="{{ implode(' ', $presets::Thumb) }}">
                        @else
                            <span class="{{ implode(' ', $presets::FileIcon) }}" aria-hidden="true">{{ app(\Gcego\Tables\Support\Icons::class)->svg('media.document', 'size-6') }}</span>
                        @endif
                        <span class="{{ implode(' ', $presets::FileName) }}">{{ \Gcego\Media\Uploads\FileInspector::displayName($item->getClientOriginalName()) }}<span class="{{ implode(' ', $presets::FileMeta) }}">{{ __('gcego-media::messages.new_file') }} · {{ \Gcego\Media\Support\Bytes::format((int) $item->getSize()) }}</span></span>
                        @php($label = \Gcego\Media\Uploads\FileInspector::displayName($item->getClientOriginalName()))
                    @else
                        @continue
                    @endif
                    @unless ($disabled)
                        <button type="button" x-on:click="$wire.set(@js($slot), null)" class="{{ implode(' ', $presets::Remove) }}">
                            {{ app(\Gcego\Tables\Support\Icons::class)->svg('media.remove', 'size-5') }}
                            <span class="sr-only">{{ __('gcego-media::messages.remove', ['name' => $label]) }}</span>
                        </button>
                    @endunless
                </li>
            @endforeach
        </ul>
    @endif
    @include('gcego-forms::fields.partials.messages')
</div>
