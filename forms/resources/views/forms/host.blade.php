<form wire:submit="submit" novalidate class="flex flex-col gap-6" data-gcego-form-host="{{ $definition }}" aria-describedby="{{ $this->getId() }}-summary">
    <div id="{{ $this->getId() }}-summary">
        @if ($errorSummary !== [])
            <div role="alert" class="{{ $summaryClass }}">
                <x-gcego-icon name="heroicon:exclamation-triangle" class="mt-0.5 size-5 shrink-0" decorative />
                <div class="min-w-0">
                    <p class="font-semibold">{{ trans_choice('gcego-forms::messages.summary', count($errorSummary), ['count' => count($errorSummary)]) }}</p>
                    <ul class="mt-1.5 flex flex-col gap-1">
                        @foreach ($errorSummary as $error)
                            <li><a href="#{{ $error['id'] }}" class="underline decoration-danger/40 underline-offset-2 transition hover:decoration-danger"><span class="font-medium">{{ $error['label'] }}:</span> {{ $error['message'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
        @if ($feedback)
            @php($tone = match ($feedback['status']) { 'success' => 'success', 'confirmation_required' => 'warning', default => 'danger' })
            <p role="status" aria-live="polite" @class(['inline-flex items-center gap-2 rounded-gcego border px-3 py-2 text-sm font-medium', 'border-success/25 bg-success-soft text-success' => $tone === 'success', 'border-warning/25 bg-warning-soft text-warning' => $tone === 'warning', 'border-danger/25 bg-danger-soft text-danger' => $tone === 'danger']) data-gcego-feedback="{{ $feedback['status'] }}"><x-gcego-icon :name="match ($tone) { 'success' => 'heroicon:check-circle', 'warning' => 'heroicon:exclamation-triangle', default => 'heroicon:x-circle' }" class="size-4 shrink-0" decorative />{{ $feedback['message'] }}</p>
        @endif
    </div>

    {!! $content !!}

    @if ($showSubmit)
        <div class="{{ implode(' ', \Gcego\Forms\Presets\FormPresets::ActionBar) }}">
            <p class="text-xs text-muted-foreground"><span class="text-danger">*</span> {{ __('gcego-forms::messages.required_hint') }}</p>
            <div class="flex items-center gap-3">
            <span wire:loading wire:target="submit" class="text-sm text-muted-foreground" role="status">{{ __('gcego-forms::messages.saving') }}</span>
            <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="{{ $submitClass }} gap-2 shadow-sm">
                <span wire:loading wire:target="submit" class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent motion-reduce:animate-none" aria-hidden="true"></span>
                {{ $submitLabel }}
            </button>
            </div>
        </div>
    @endif
</form>
