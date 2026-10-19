{{-- gcego-notifications::preferences.preferences — switches per category and channel. --}}
@php($presets = \Gcego\Notifications\Presets\NotificationPresets::class)
<div class="{{ implode(' ', $presets::InboxCard) }} overflow-x-auto">
    <table class="w-full text-sm">
        <caption class="sr-only">{{ __('gcego-notifications::messages.preferences') }}</caption>
        <thead>
            <tr class="border-b border-gcego-border text-start">
                <th scope="col" class="px-4 py-3 text-start font-semibold text-gcego-text">{{ __('gcego-notifications::messages.category') }}</th>
                @foreach ($channels as $channel)
                    <th scope="col" class="px-4 py-3 text-start font-semibold text-gcego-text">{{ __('gcego-notifications::messages.channels.'.$channel) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gcego-border">
            @foreach ($categories as $category)
                <tr wire:key="preference-{{ $category->id }}">
                    <th scope="row" class="px-4 py-3 text-start font-medium text-gcego-text">{{ $category->label() }}</th>
                    @foreach ($channels as $channel)
                        @php($on = $preferences->allows($viewer->user, $category->id, $channel))
                        @php($locked = ! $preferences->changeable($category->id, $channel))
                        <td class="px-4 py-3">
                            <button type="button" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}" @disabled($locked)
                                wire:click="toggle('{{ $category->id }}', '{{ $channel }}')"
                                aria-label="{{ __('gcego-notifications::messages.preference', ['category' => $category->label(), 'channel' => __('gcego-notifications::messages.channels.'.$channel)]) }}"
                                class="{{ implode(' ', $presets::Link) }} disabled:opacity-50">
                                {{ $on ? __('gcego-notifications::messages.on') : __('gcego-notifications::messages.off') }}@if ($locked) ({{ __('gcego-notifications::messages.required') }})@endif
                            </button>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
