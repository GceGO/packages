{{-- gcego-workflows::pages.instance — one instance: its panel and history. --}}
@php($p = \Gcego\Workflows\Presets\WorkflowPresets::class)
<div class="{{ implode(' ', $p::Page) }}">
    <h1 class="{{ implode(' ', $p::Title) }}">{{ $labels->title($record) }}</h1>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <livewire:gcego-workflow-panel :instance="$record->ulid" :key="'panel-'.$record->ulid" />
        </div>
        <div>
            <livewire:gcego-workflow-timeline :instance="$record->ulid" :key="'timeline-'.$record->ulid" />
        </div>
    </div>
</div>
