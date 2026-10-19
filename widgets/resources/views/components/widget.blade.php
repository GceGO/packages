@props(['name', 'record' => null])
{{-- <x-gcego-widgets::widget name="total_students" />: static Blade, or the Livewire host when the widget needs it. --}}
@php($widgets = app(\Gcego\Widgets\WidgetManager::class))
@if ($widgets->resolve($name)->needsLivewire())
    @livewire('gcego-widget', ['widget' => $name, 'record' => $record], key('gcego-widget-'.$name.'-'.$record))
@else
    {{ $widgets->render($name, record: $record) }}
@endif
