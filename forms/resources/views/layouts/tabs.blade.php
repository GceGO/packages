{{-- WAI-ARIA tabs: every panel stays in the DOM (all tabs are validated and submitted); arrow keys move focus. --}}
<div class="{{ $styles('root') }} flex flex-col gap-5" x-data="{ active: 0, count: {{ count($labels) }}, move(step) { this.active = (this.active + step + this.count) % this.count; this.$nextTick(() => this.$refs['tab' + this.active].focus()); } }">
    <div role="tablist" class="{{ implode(' ', $presets::TabList) }}" x-on:keydown.arrow-right.prevent="move(1)" x-on:keydown.arrow-left.prevent="move(-1)" x-on:keydown.home.prevent="active = 0; $refs.tab0.focus()" x-on:keydown.end.prevent="active = count - 1; $refs['tab' + (count - 1)].focus()">
        @foreach ($labels as $index => $tabLabel)
            <button type="button" role="tab" id="{{ $context->domId('tab-'.$index) }}" aria-controls="{{ $context->domId('panel-'.$index) }}" x-ref="tab{{ $index }}" x-bind:aria-selected="(active === {{ $index }}).toString()" x-bind:tabindex="active === {{ $index }} ? 0 : -1" x-on:click="active = {{ $index }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" class="{{ implode(' ', $presets::Tab) }}">{{ $tabLabel }}</button>
        @endforeach
    </div>
    @foreach ($children as $index => $panel)
        <div role="tabpanel" id="{{ $context->domId('panel-'.$index) }}" aria-labelledby="{{ $context->domId('tab-'.$index) }}" tabindex="0" class="rounded-gcego focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring" x-show="active === {{ $index }}" @if ($index > 0) x-cloak @endif>{{ $panel }}</div>
    @endforeach
</div>
