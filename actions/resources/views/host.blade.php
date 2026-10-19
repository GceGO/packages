{{-- gcego/actions host. Classes come from gcego/chroma slots; nothing here is trusted client input. --}}
<div class="inline-flex flex-wrap items-center gap-2" data-gcego-actions="{{ $definition }}">
    @foreach ($buttons as $button)
        <button
            type="button"
            class="{{ $button['class'] }}"
            data-gcego-action="{{ $button['name'] }}"
            @if ($button['bulk'])
                x-on:click="$wire.runBulk('{{ $button['name'] }}', window.GcegoSelection?.('{{ $definition }}') ?? [])"
            @else
                wire:click="run('{{ $button['name'] }}')"
            @endif
            wire:loading.attr="disabled"
            wire:target="run, runBulk, submit"
            @disabled($button['disabled'])
        >{{ $button['icon'] }}<span>{{ $button['label'] }}</span></button>
    @endforeach

    @if ($feedback)
        <p role="status" aria-live="polite" class="text-sm text-gcego-text-muted" data-gcego-feedback="{{ $feedback['status'] }}">{{ $feedback['message'] }}</p>
    @endif

    @if ($dialog)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:key="dialog-{{ $dialog['action'] }}">
            <div
                role="{{ $dialog['destructive'] ? 'alertdialog' : 'dialog' }}"
                aria-modal="true"
                aria-labelledby="{{ $this->getId() }}-dialog-title"
                @if ($dialog['message'] !== '') aria-describedby="{{ $this->getId() }}-dialog-message" @endif
                tabindex="-1"
                x-data
                x-init="$nextTick(() => ($el.querySelector('[data-autofocus]') ?? $el).focus())"
                x-on:keydown.escape.prevent="$wire.cancel()"
                class="{{ $dialogClass }} w-full max-w-md p-6 shadow-gcego-card"
            >
                <h2 id="{{ $this->getId() }}-dialog-title" class="text-lg font-semibold text-gcego-text">{{ $dialog['title'] }}</h2>

                @if ($dialog['message'] !== '')
                    <p id="{{ $this->getId() }}-dialog-message" class="mt-2 text-sm text-gcego-text-muted">{{ $dialog['message'] }}</p>
                @endif

                <form wire:submit="submit" class="mt-4 flex flex-col gap-4">
                    @if ($dialogForm !== null)
                        {!! $dialogForm !!}
                    @endif
                    @foreach ($dialogForm === null ? $dialog['fields'] : [] as $field)
                        <div class="flex flex-col gap-1.5">
                            <label for="{{ $this->getId() }}-{{ $field['name'] }}" class="text-sm font-medium text-gcego-text">{{ $field['label'] }}</label>
                            <input
                                id="{{ $this->getId() }}-{{ $field['name'] }}"
                                wire:model="input.{{ $field['name'] }}"
                                @if ($loop->first) data-autofocus @endif
                                @error('input.'.$field['name']) aria-invalid="true" aria-describedby="{{ $this->getId() }}-{{ $field['name'] }}-error" @enderror
                                class="min-h-10 rounded-gcego border border-gcego-border-strong bg-gcego-surface px-3 py-2 text-sm text-gcego-text focus-visible:outline-2 focus-visible:outline-gcego-ring aria-invalid:border-gcego-danger"
                            >
                            @error('input.'.$field['name'])
                                <p id="{{ $this->getId() }}-{{ $field['name'] }}-error" class="text-sm text-gcego-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="cancel" class="inline-flex min-h-10 items-center rounded-gcego border border-gcego-border-strong bg-gcego-surface px-4 text-sm font-medium text-gcego-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gcego-ring">{{ $dialog['cancel'] }}</button>
                        <button
                            type="submit"
                            @if ($dialog['fields'] === []) data-autofocus @endif
                            wire:loading.attr="disabled"
                            wire:target="submit"
                            class="inline-flex min-h-10 items-center rounded-gcego px-4 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gcego-ring disabled:opacity-60 {{ $dialog['destructive'] ? 'bg-gcego-danger text-gcego-danger-foreground' : 'bg-gcego-primary text-gcego-primary-foreground' }}"
                        >{{ $dialog['confirm'] }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
