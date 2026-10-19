<fieldset class="{{ $styles('root') }} {{ $span }}" data-gcego-field="{{ $path }}" @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
    <legend {{ $styles->attribute('label') }}>{{ $label }}@if ($required)<span aria-hidden="true" class="{{ implode(' ', $presets::Required) }}"> *</span>@endif</legend>
    <ol class="mt-2 flex flex-col gap-3" aria-live="polite">
        @foreach ($items as $key)
            @php($itemLabel = __('gcego-forms::messages.item', ['label' => $label, 'number' => $loop->iteration]))
            <li class="{{ $styles('item') }} {{ implode(' ', $presets::RepeaterItem) }}" wire:key="{{ $path }}.{{ $key }}" data-gcego-item="{{ $key }}" aria-label="{{ $itemLabel }}">
                <div class="{{ implode(' ', $presets::RepeaterHeader) }}">
                    <span class="inline-flex items-center gap-2 text-sm font-medium text-foreground"><span class="{{ implode(' ', $presets::RepeaterIndex) }}">{{ $loop->iteration }}</span>{{ $label }}</span>
                    @if ($live && ! $disabled)
                        <div class="flex items-center gap-0.5">
                            @if ($field->isReorderable())
                                <button type="button" wire:click="moveItem('{{ $path }}', '{{ $key }}', -1)" @disabled($loop->first) class="{{ implode(' ', $presets::IconButton) }}" title="{{ __('gcego-forms::messages.move_up') }}"><x-gcego-icon name="heroicon:arrow-up" class="size-4" decorative /><span class="sr-only">{{ __('gcego-forms::messages.move_up') }} {{ $itemLabel }}</span></button>
                                <button type="button" wire:click="moveItem('{{ $path }}', '{{ $key }}', 1)" @disabled($loop->last) class="{{ implode(' ', $presets::IconButton) }}" title="{{ __('gcego-forms::messages.move_down') }}"><x-gcego-icon name="heroicon:arrow-down" class="size-4" decorative /><span class="sr-only">{{ __('gcego-forms::messages.move_down') }} {{ $itemLabel }}</span></button>
                            @endif
                            <button type="button" wire:click="removeItem('{{ $path }}', '{{ $key }}')" @disabled(count($items) <= $field->minItems()) class="{{ implode(' ', $presets::DangerIconButton) }}" title="{{ __('gcego-forms::messages.remove') }}"><x-gcego-icon name="heroicon:trash" class="size-4" decorative /><span class="sr-only">{{ __('gcego-forms::messages.remove') }} {{ $itemLabel }}</span></button>
                        </div>
                    @endif
                </div>
                {{ $renderItem($key) }}
            </li>
        @endforeach
    </ol>
    @if ($live && ! $disabled)
        <button type="button" wire:click="addItem('{{ $path }}')" @disabled($field->maxItems() !== null && count($items) >= $field->maxItems()) class="mt-3 {{ implode(' ', $presets::AddButton) }}">
            <x-gcego-icon name="heroicon:plus" class="size-4" decorative />{{ $field->get('add_label') !== null ? __($field->get('add_label')) : __('gcego-forms::messages.add', ['label' => $label]) }}
        </button>
    @endif
    @if ($errors !== [])
        <p id="{{ $id }}-error" {{ $styles->attribute('error') }}><x-gcego-icon name="heroicon:exclamation-circle" class="mt-0.5 size-4 shrink-0" decorative /><span>{{ implode(' ', $errors) }}</span></p>
    @endif
</fieldset>
