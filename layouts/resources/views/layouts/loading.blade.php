{{-- gcego-layouts::layouts.loading — a skeleton of stable size --}}
<div id="{{ $layout->id() }}" role="status" aria-live="polite" aria-busy="true"{{ $layout->class('root', $grid ? 'grid grid-cols-1 gap-4 sm:grid-cols-3' : implode(' ', $presets::Loading)) }}>
    <span class="sr-only">{{ $layout->title() ?? __('gcego-layouts::messages.loading') }}</span>
    @foreach ($blocks as $block)
        <div aria-hidden="true"{{ $layout->class('skeleton', implode(' ', $presets::Skeleton), $block) }}></div>
    @endforeach
</div>
