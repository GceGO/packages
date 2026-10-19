{{-- gcego-layouts::layouts.accordion — native <details> items --}}
<div id="{{ $layout->id() }}"{{ $layout->class('root', implode(' ', $presets::Accordion)) }}{{ $layout->key() }}>
    @foreach ($layout->regions as $item)
        @php($icon = $item->icon('size-4'))
        <details id="{{ $item->id() }}"{{ $item->class('root', $layout->classes('item', implode(' ', $presets::AccordionItem))) }}@if ($group !== null) name="{{ $group }}"@endif @if ($item->component instanceof \Gcego\Layouts\Components\AccordionItem && $item->component->isOpen()) open @endif @if ($layout->live()) wire:ignore.self @endif{{ $item->key() }}>
            <summary{{ $layout->class('summary', implode(' ', [...$presets::Summary, ...$presets::AccordionSummary])) }}>
                <span class="flex min-w-0 items-center gap-3">
                    @if ($icon !== null)
                        <span aria-hidden="true" class="shrink-0 text-gcego-text-muted">{{ $icon }}</span>
                    @endif
                    <span class="min-w-0">
                        <span class="block">{{ $item->title() }}</span>
                        @if ($item->description() !== null)
                            <span class="block text-xs font-normal text-gcego-text-muted">{{ $item->description() }}</span>
                        @endif
                    </span>
                </span>
                @include('gcego-layouts::partials.chevron')
            </summary>
            <div{{ $item->class('body', implode(' ', $presets::AccordionBody)) }}>{{ $item->content() }}</div>
        </details>
    @endforeach
</div>
