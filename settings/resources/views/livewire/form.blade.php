{{-- gcego-settings::livewire.form — one settings page, rendered field by field through gcego/forms. --}}
@php($p = \Gcego\Settings\Presets\SettingsPresets::class)
<form
    wire:submit="save"
    novalidate
    class="{{ $styles->classes('group', $p::Group) }}"
    data-gcego-settings-form="{{ $page }}"
    x-data="{ dirty: false, confirming: null, confirmingLabel: '' }"
    x-on:input="dirty = true"
    x-on:change="dirty = true"
    x-on:gcego-settings-saved.window="dirty = false"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="{{ $styles->classes('scope', $p::Scope) }}" data-gcego-settings-scope="{{ $screen->context->target->value }}">
            <span class="text-gcego-text-muted">{{ __('gcego-settings::messages.editing') }}</span>
            <span>{{ $screen->context->target->label().($screen->context->target === \Gcego\Settings\Scopes\SettingScope::Tenant && $screen->context->tenant?->name ? ' · '.$screen->context->tenant->name : '') }}</span>
        </p>
        <p x-show="dirty" x-cloak class="{{ implode(' ', $p::Unsaved) }}" role="status">{{ __('gcego-settings::messages.unsaved') }}</p>
    </div>

    <div role="status" aria-live="polite" aria-atomic="true">
        @if ($feedback)
            <p class="{{ implode(' ', $p::Status) }}" data-gcego-feedback="{{ $feedback['status'] }}">{{ $feedback['message'] }}</p>
        @endif
    </div>
    @if ($general !== [])
        <div role="alert" class="{{ $styles->classes('error', $p::Error) }}">{{ implode(' ', $general) }}</div>
    @endif

    @foreach ($sections as $section)
        <section class="{{ $styles->classes('section', $p::Section) }}" @if ($section['name']) aria-labelledby="gcego-settings-section-{{ $page }}-{{ $section['name'] }}" @else aria-label="{{ $section['title'] }}" @endif wire:key="section-{{ $section['name'] ?? '_' }}-{{ $loop->index }}">
            @if ($section['name'])
                <h2 id="gcego-settings-section-{{ $page }}-{{ $section['name'] }}" class="{{ implode(' ', $p::SectionTitle) }}">{{ $section['title'] }}</h2>
                @if ($section['description'])
                    <p class="{{ $styles->classes('description', $p::Description) }} mt-1">{{ $section['description'] }}</p>
                @endif
            @endif
            <div class="{{ implode(' ', $p::SectionGrid) }} {{ $p::Columns[$section['columns']] ?? '' }}">
                @foreach ($section['rows'] as $row)
                    <div class="{{ $styles->classes('field', $p::Field) }} {{ $p::Spans[$row['span']] ?? '' }}" data-gcego-setting="{{ $row['key'] }}" wire:key="setting-{{ $row['key'] }}">
                        {{ $row['html'] }}
                        <div class="{{ implode(' ', $p::FieldMeta) }}">
                            @if ($row['source'] === 'override')
                                <span class="{{ $styles->classes('override', [...$p::Badge, ...$p::OverrideBadge]) }}" data-gcego-source="override">{{ __('gcego-settings::messages.source.override', ['scope' => $screen->context->target->label()]) }}</span>
                            @elseif ($row['source'] === 'inherited')
                                <span class="{{ $styles->classes('inherited', [...$p::Badge, ...$p::InheritedBadge]) }}" data-gcego-source="inherited">{{ __('gcego-settings::messages.source.inherited', ['scope' => $row['inherited']]) }}</span>
                            @else
                                <span class="{{ $styles->classes('inherited', [...$p::Badge, ...$p::InheritedBadge]) }}" data-gcego-source="default">{{ __('gcego-settings::messages.source.default') }}</span>
                            @endif
                            @if ($row['sensitive'])
                                <span class="{{ $styles->badge($p::SensitiveBadge) }}">{{ $row['has_secret'] ? __('gcego-settings::messages.secret_badge_set') : __('gcego-settings::messages.secret_badge_unset') }}</span>
                            @endif
                            @if ($row['can_reset'])
                                <button
                                    type="button"
                                    class="{{ implode(' ', $p::Link) }}"
                                    x-on:click="confirming = @js($row['key']); confirmingLabel = @js($row['label']); $nextTick(() => $refs.confirmReset.focus())"
                                    aria-haspopup="dialog"
                                >{{ __('gcego-settings::messages.reset', ['setting' => $row['label']]) }}</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($editable)
        <div class="{{ $styles->classes('actions', $p::Actions) }}">
            <span wire:loading wire:target="save,resetSetting" class="{{ $styles->classes('loading', $p::Loading) }}" role="status">{{ __('gcego-settings::messages.saving') }}</span>
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="{{ $styles->button('primary') }}">{{ __('gcego-settings::messages.save') }}</button>
        </div>
    @endif

    <div
        x-show="confirming !== null"
        x-cloak
        class="{{ implode(' ', $p::Dialog) }}"
        role="dialog"
        aria-modal="true"
        aria-labelledby="gcego-settings-reset-title-{{ $page }}"
        aria-describedby="gcego-settings-reset-body-{{ $page }}"
        x-on:keydown.escape.window="confirming = null"
    >
        <div class="{{ implode(' ', $p::DialogPanel) }}" x-on:click.outside="confirming = null">
            <h2 id="gcego-settings-reset-title-{{ $page }}" class="{{ implode(' ', $p::SectionTitle) }}">{{ __('gcego-settings::messages.reset_title') }}</h2>
            <p id="gcego-settings-reset-body-{{ $page }}" class="mt-2 text-sm text-gcego-text-muted" x-text="@js(__('gcego-settings::messages.reset_body', ['setting' => ':setting'])).replace(':setting', confirmingLabel)"></p>
            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <button type="button" class="{{ $styles->button() }}" x-on:click="confirming = null">{{ __('gcego-settings::messages.cancel') }}</button>
                <button type="button" x-ref="confirmReset" class="{{ $styles->button('destructive') }}" x-on:click="$wire.resetSetting(confirming); confirming = null">{{ __('gcego-settings::messages.reset_confirm') }}</button>
            </div>
        </div>
    </div>
</form>
