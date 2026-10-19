<div class="relative" x-data="{ open: false, name: '', renaming: null, newName: '' }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-expanded="false" aria-controls="{{ $frame->id('views') }}" class="{{ implode(' ', $presets::ToolButton) }}">
        {{ $icons->svg('tables.saved-views', 'size-4') }}<span class="hidden sm:inline">{{ __('gcego-tables::messages.views') }}</span><span class="sr-only sm:hidden">{{ __('gcego-tables::messages.views') }}</span>
        @if ($frame->activeView !== null)<span class="size-1.5 rounded-full bg-primary" aria-hidden="true"></span>@endif
    </button>
    <div id="{{ $frame->id('views') }}" x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95 -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
        class="{{ implode(' ', $presets::Panel) }}" role="group" aria-label="{{ __('gcego-tables::messages.views') }}">
        <p class="{{ implode(' ', $presets::PanelHeading) }}">{{ __('gcego-tables::messages.views') }}</p>
        @if ($frame->views === [])
            <p class="px-2 pb-1 text-sm text-muted-foreground">{{ __('gcego-tables::messages.no_views') }}</p>
        @else
            <ul class="flex max-h-72 flex-col gap-1 overflow-y-auto">
                @foreach ($frame->views as $view)
                    <li class="flex items-center justify-between gap-2 rounded-gcego px-1 transition hover:bg-accent" wire:key="{{ $frame->id('view-'.$view['id']) }}">
                        <template x-if="renaming !== {{ \Illuminate\Support\Js::from($view['id']) }}">
                            <button type="button" wire:click="loadView({{ \Illuminate\Support\Js::from($view['id']) }})" class="{{ implode(' ', $presets::RowButton) }} flex-1 justify-start truncate aria-[current=true]:bg-primary-soft aria-[current=true]:text-primary" @if ($frame->activeView === $view['id']) aria-current="true" @endif>
                                {{ $view['name'] }}@if ($view['default'])<span class="sr-only"> ({{ __('gcego-tables::messages.default_view') }})</span>@endif
                            </button>
                        </template>
                        <template x-if="renaming === {{ \Illuminate\Support\Js::from($view['id']) }}">
                            <form class="flex flex-1 gap-1" x-on:submit.prevent="$wire.renameView({{ \Illuminate\Support\Js::from($view['id']) }}, newName); renaming = null">
                                <label class="sr-only" for="{{ $frame->id('rename-'.$view['id']) }}">{{ __('gcego-tables::messages.view_name') }}</label>
                                <input id="{{ $frame->id('rename-'.$view['id']) }}" x-model="newName" maxlength="60" class="{{ implode(' ', $presets::FilterControl) }} h-8">
                                <button type="submit" class="{{ implode(' ', $presets::RowButton) }}">{{ __('gcego-tables::messages.save') }}</button>
                            </form>
                        </template>
                        <span class="flex gap-1">
                            <button type="button" wire:click="setDefaultView({{ $view['default'] ? 'null' : \Illuminate\Support\Js::from($view['id']) }})" aria-pressed="{{ $view['default'] ? 'true' : 'false' }}" class="rounded-gcego-sm p-1.5 text-muted-foreground transition hover:bg-surface hover:text-foreground aria-pressed:text-warning" aria-label="{{ __('gcego-tables::messages.make_default', ['view' => $view['name']]) }}">{{ $icons->svg('tables.default-view') }}</button>
                            <button type="button" x-on:click="renaming = {{ \Illuminate\Support\Js::from($view['id']) }}; newName = {{ \Illuminate\Support\Js::from($view['name']) }}" class="rounded-gcego-sm p-1.5 text-muted-foreground transition hover:bg-surface hover:text-foreground" aria-label="{{ __('gcego-tables::messages.rename_view', ['view' => $view['name']]) }}">{{ $icons->svg('actions.rename') }}</button>
                            <button type="button" wire:click="deleteView({{ \Illuminate\Support\Js::from($view['id']) }})" class="rounded-gcego-sm p-1.5 text-muted-foreground transition hover:bg-danger-soft hover:text-danger" aria-label="{{ __('gcego-tables::messages.delete_view', ['view' => $view['name']]) }}">{{ $icons->svg('actions.delete') }}</button>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
        <form class="mt-2 flex gap-2 border-t border-border px-1 pt-2" x-on:submit.prevent="if (name.trim() !== '') { $wire.saveView(name); name = '' }">
            <label for="{{ $frame->id('view-name') }}" class="sr-only">{{ __('gcego-tables::messages.view_name') }}</label>
            <input id="{{ $frame->id('view-name') }}" x-model="name" maxlength="60" placeholder="{{ __('gcego-tables::messages.view_name') }}" class="{{ implode(' ', $presets::FilterControl) }} h-9">
            <button type="submit" class="{{ implode(' ', $presets::ToolButton) }} shrink-0">{{ __('gcego-tables::messages.save_view') }}</button>
        </form>
    </div>
</div>
