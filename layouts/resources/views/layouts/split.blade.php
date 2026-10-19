{{-- gcego-layouts::layouts.split — Split and Sidebar: regions in source order --}}
<div id="{{ $layout->id() }}"{{ $layout->class('root', $split) }}{{ $layout->key() }}>
    @foreach ($layout->regions as $index => $region)
        @php($element = $region->component instanceof \Gcego\Layouts\Components\Main ? $region->component->element() : 'aside')
        <{{ $element }} id="{{ $region->id() }}"@if ($region->title() !== null && $element !== 'div') aria-label="{{ $region->title() }}"@endif{{ $region->class('root', implode(' ', [...$presets::Region, ...$presets::Flow]), $presets::Gaps['default'][$config->defaultGap], $regionClasses[$index] ?? '') }}{{ $region->key() }}>{{ $region->content() }}</{{ $element }}>
    @endforeach
</div>
