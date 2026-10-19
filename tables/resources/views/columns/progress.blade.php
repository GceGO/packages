@if ($cell['percent'] === null)
    <span class="text-muted-foreground">{{ $column->placeholder() }}</span>
@else
    <div class="flex items-center gap-2">
        <div class="{{ implode(' ', $presets::ProgressTrack) }}" role="progressbar" aria-valuemin="{{ $cell['min'] }}" aria-valuemax="{{ $cell['max'] }}" aria-valuenow="{{ max($cell['min'], min($cell['max'], (float) $cell['now'])) }}" aria-valuetext="{{ $cell['text'] }}" aria-label="{{ __($column->label()) }}">
            <div class="{{ implode(' ', $presets::ProgressBar) }}" style="width: {{ $cell['percent'] }}%"></div>
        </div>
        <span class="w-12 text-right text-xs tabular-nums text-muted-foreground">{{ $cell['text'] }}</span>
    </div>
@endif
