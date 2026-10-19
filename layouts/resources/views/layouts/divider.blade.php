{{-- gcego-layouts::layouts.divider --}}
@if ($vertical)
    <div{{ $layout->class('root', implode(' ', $presets::VerticalDivider), $spacing) }}@if ($decorative) aria-hidden="true"@else role="separator" aria-orientation="vertical"@endif></div>
@elseif ($layout->title() !== null)
    <div{{ $layout->class('root', implode(' ', $presets::LabelledDivider), $spacing) }}@unless ($decorative) role="separator" aria-label="{{ $layout->title() }}"@endunless>{{ $layout->title() }}</div>
@elseif ($decorative)
    <div aria-hidden="true"{{ $layout->class('root', implode(' ', $presets::Divider), $spacing) }}></div>
@else
    <hr{{ $layout->class('root', implode(' ', $presets::Divider), $spacing) }}>
@endif
