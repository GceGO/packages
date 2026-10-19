@unless ($skip)
    <div class="{{ $styles('root') }} {{ $entry->spanClass($columns) }}" data-gcego-entry="{{ $path }}">
        <dt class="{{ $styles('label') }}">{{ __($entry->label()) }}</dt>
        <dd class="{{ $styles('value') }}">
            @if ($masked)
                <span class="font-mono text-gcego-text-muted" aria-label="{{ __('gcego-infolists::messages.masked') }}">••••••</span>
            @else
                @include($entry->view())
            @endif
            @if ($entry->description())
                <p class="mt-1 {{ $styles('description') }}">{{ __($entry->description()) }}</p>
            @endif
        </dd>
    </div>
@endunless
