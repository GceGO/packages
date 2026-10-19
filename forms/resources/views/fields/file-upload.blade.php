<div class="{{ $styles('root') }} {{ $span }}" data-gcego-field="{{ $path }}" x-data="{ uploading: false, progress: 0, chosen: [], over: false }" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-cancel="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress">
    @include('gcego-forms::fields.partials.label')
    {{-- The native file input covers the drop zone, so clicking, keyboard and drag-and-drop all reach it. --}}
    <div class="{{ implode(' ', $presets::Dropzone) }}" x-bind:class="over && 'border-primary! bg-primary-soft!'" x-on:dragenter="over = true" x-on:dragleave="over = false" x-on:drop="over = false">
        <input
            id="{{ $id }}"
            type="file"
            {{ $binding }}
            @if ($field->isMultiple()) multiple @endif
            @if ($field->accepted() !== []) accept="{{ implode(',', array_map(fn ($ext) => '.'.$ext, $field->accepted())) }}" @endif
            x-on:change="chosen = Array.from($el.files).map((file) => file.name)"
            @include('gcego-forms::fields.partials.aria')
            class="absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
        >
        <span class="inline-flex size-10 items-center justify-center rounded-full border border-border bg-surface text-muted-foreground shadow-xs" aria-hidden="true">
            <x-gcego-icon name="heroicon:arrow-up-tray" class="size-5" decorative />
        </span>
        <p class="text-sm text-muted-foreground"><span class="font-semibold text-primary">{{ __('gcego-forms::messages.upload_cta') }}</span> {{ __('gcego-forms::messages.upload_drop') }}</p>
        @if ($field->accepted() !== [])
            <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ __('gcego-forms::messages.upload_types', ['types' => implode(', ', $field->accepted())]) }}</p>
        @endif
        <ul x-show="chosen.length" x-cloak class="mt-1 flex flex-wrap justify-center gap-1.5" aria-live="polite">
            <template x-for="name in chosen" x-bind:key="name">
                <li class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-border bg-surface px-2.5 py-1 text-xs font-medium text-foreground shadow-xs"><x-gcego-icon name="heroicon:document" class="size-3.5 text-muted-foreground" decorative /><span class="truncate" x-text="name"></span></li>
            </template>
        </ul>
    </div>
    <div x-show="uploading" x-cloak role="progressbar" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="progress" aria-label="{{ __('gcego-forms::messages.uploading') }}" class="h-1.5 w-full overflow-hidden rounded-full bg-surface-muted">
        <div class="h-full rounded-full bg-primary transition-[width] duration-200" x-bind:style="'width: ' + progress + '%'"></div>
    </div>
    @if (is_string($value))
        <p class="inline-flex items-center gap-2 text-sm text-muted-foreground"><x-gcego-icon name="heroicon:paper-clip" class="size-4" decorative />{{ __('gcego-forms::messages.current_file', ['name' => basename($value)]) }}</p>
    @endif
    @include('gcego-forms::fields.partials.messages')
</div>
