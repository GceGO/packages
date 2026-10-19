{{-- <x-gcego-layouts::layout name="dashboard" :record="$student->id" /> — resolves, authorizes and renders a registered layout --}}
@props(['name', 'record' => null, 'live' => true])
{{ app(\Gcego\Layouts\LayoutManager::class)->render((string) $name, is_int($record) || is_string($record) ? $record : null, (bool) $live) }}
