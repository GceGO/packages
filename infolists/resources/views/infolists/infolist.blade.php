@php
    $title = $infolist?->get('label');
    $description = $infolist?->get('description');
    $headingId = $frame?->id('heading');
@endphp
<article class="{{ $styles('root') }}" data-gcego-infolist="{{ $infolist?->infolistName() }}" @if ($title) aria-labelledby="{{ $headingId }}" @endif>
    @if ($title || $description || $hasActions)
        {{-- Actions alone (no title): a plain end-aligned row instead of an empty bordered header bar. --}}
        <header class="{{ $title || $description ? $styles('header') : implode(' ', \Gcego\Infolists\Presets\InfolistPresets::ActionsHeader) }}">
            <div class="flex min-w-0 flex-col gap-1">
                @if ($title)
                    <h2 id="{{ $headingId }}" class="{{ $styles('title') }}">{{ __($title) }}</h2>
                @endif
                @if ($description)
                    <p class="{{ $styles('description') }}">{{ __($description) }}</p>
                @endif
            </div>
            @if ($hasActions)
                <div class="flex flex-wrap items-center gap-2">
                    @livewire('gcego-actions', ['definition' => $frame->definition, 'placement' => 'record', 'record' => $recordKey], key($frame->id('actions')))
                </div>
            @endif
        </header>
    @endif
    <div class="{{ $styles('body') }}">
        @forelse ($groups as $group)
            @if ($group['type'] === 'entries')
                <dl class="{{ $grid }}">@foreach ($group['html'] as $html){!! $html !!}@endforeach</dl>
            @else
                @foreach ($group['html'] as $html){!! $html !!}@endforeach
            @endif
        @empty
            <div class="{{ $styles('empty') }}" role="status" data-gcego-infolist-empty>
                {{ $icons->svg('infolists.empty', 'size-8') }}
                <p>{{ __($infolist?->get('empty_heading') ?? 'gcego-infolists::messages.empty') }}</p>
            </div>
        @endforelse
    </div>
</article>
