@props(['definition', 'record'])
{{-- <x-gcego-infolists::infolist definition="subjects" :record="$subject->id" />: server-side, no Livewire. --}}
{{ app(\Gcego\Infolists\InfolistManager::class)->render($definition, $record) }}
