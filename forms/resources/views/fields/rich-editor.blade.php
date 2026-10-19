@if ($field->editor() !== null)
    @include(app($field->editor())->view(), ['editor' => app($field->editor())])
@else
    {{-- Default adapter: a plain textarea. HTML is sanitized on the server before it is stored. --}}
    <div class="{{ $styles('root') }} {{ $span }}" data-gcego-field="{{ $path }}">
        @include('gcego-forms::fields.partials.label')
        <textarea id="{{ $id }}" {{ $binding }} rows="8" @include('gcego-forms::fields.partials.aria') class="{{ $styles('input') }} min-h-40 font-mono text-sm">@unless ($live){{ is_string($value) ? $value : '' }}@endunless</textarea>
        <p class="text-xs text-muted-foreground">{{ __('gcego-forms::messages.rich_fallback', ['tags' => implode(', ', $field->allowedElements())]) }}</p>
        @include('gcego-forms::fields.partials.messages')
    </div>
@endif
