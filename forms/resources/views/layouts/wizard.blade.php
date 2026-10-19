{{-- One step at a time; "Next" validates the current step on the server. --}}
<div class="{{ $styles('root') }} flex flex-col gap-6">
    <ol class="flex flex-col gap-3 sm:flex-row sm:items-center" aria-label="{{ __('gcego-forms::messages.steps') }}">
        @foreach ($labels as $index => $stepLabel)
            @php($done = $index < $step)
            <li class="{{ implode(' ', $presets::Step) }} {{ $loop->last ? 'sm:flex-none' : '' }}" @if ($index === $step) aria-current="step" @endif>
                <span @class([implode(' ', $presets::StepMarker), 'border-primary bg-primary text-primary-foreground' => $done, 'border-primary bg-surface text-primary ring-4 ring-primary/15' => $index === $step, 'border-border-strong bg-surface text-muted-foreground' => $index > $step])>
                    @if ($done)
                        <x-gcego-icon name="heroicon:check" class="size-4" :stroke-width="3" decorative /><span class="sr-only">{{ __('gcego-forms::messages.completed') }}</span>
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <span class="flex min-w-0 flex-col">
                    <span class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{{ __('gcego-forms::messages.step_of', ['number' => $index + 1, 'total' => count($labels)]) }}</span>
                    <span class="truncate font-medium">{{ $stepLabel }}</span>
                </span>
                @unless ($loop->last)
                    <span aria-hidden="true" @class([implode(' ', $presets::StepConnector), 'bg-primary' => $done, 'bg-border' => ! $done])></span>
                @endunless
            </li>
        @endforeach
    </ol>
    <div>{{ $children->render($step) }}</div>
    @if ($live)
        <div class="flex items-center justify-between gap-2 border-t border-border pt-4">
            <button type="button" wire:click="previousStep" @disabled($step === 0) class="{{ implode(' ', $presets::SmallButton) }}"><x-gcego-icon name="heroicon:arrow-left" class="size-4" decorative />{{ __('gcego-forms::messages.previous') }}</button>
            @if ($step < count($labels) - 1)
                <button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep" class="{{ implode(' ', $presets::PrimaryButton) }}">
                    <span wire:loading.remove wire:target="nextStep">{{ __('gcego-forms::messages.next') }}</span>
                    <span wire:loading wire:target="nextStep" class="{{ implode(' ', $presets::Spinner) }}" aria-hidden="true"></span>
                    <x-gcego-icon name="heroicon:arrow-right" class="size-4" decorative />
                </button>
            @endif
        </div>
    @endif
</div>
