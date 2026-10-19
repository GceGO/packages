@if ($href !== null)
<a href="{{ $href }}" title="{{ $label }}" {{ $attributes->class($classes()) }}>{{ $svg() }}<span class="sr-only">{{ $label }}</span></a>
@else
<button type="{{ $type }}" title="{{ $label }}" {{ $attributes->class($classes()) }}>{{ $svg() }}<span class="sr-only">{{ $label }}</span></button>
@endif
