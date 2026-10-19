@unless ($skip)
    {{-- WAI-ARIA tabs: roving tabindex, arrow keys, Home and End. Panels stay in the DOM. --}}
    <div class="{{ $styles('root') }} flex flex-col gap-4" x-data="{ active: 0, count: {{ count($panels) }}, go(index) { this.active = (index + this.count) % this.count; this.$nextTick(() => this.$refs['tab' + this.active].focus()); } }" data-gcego-tabs>
        <div role="tablist" class="{{ implode(' ', $presets::TabList) }}" x-on:keydown.arrow-right.prevent="go(active + 1)" x-on:keydown.arrow-left.prevent="go(active - 1)" x-on:keydown.home.prevent="go(0)" x-on:keydown.end.prevent="go(count - 1)">
            @foreach ($panels as $index => $panel)
                <button type="button" role="tab" id="{{ $frame->id('tab-'.$context->path().'-'.$index) }}" aria-controls="{{ $frame->id('panel-'.$context->path().'-'.$index) }}" x-ref="tab{{ $index }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" tabindex="{{ $index === 0 ? 0 : -1 }}" x-bind:aria-selected="(active === {{ $index }}).toString()" x-bind:tabindex="active === {{ $index }} ? 0 : -1" x-on:click="active = {{ $index }}" class="{{ implode(' ', $presets::Tab) }}">
                    {{ $icons->svg(is_string($panel['icon']) ? $panel['icon'] : null) }}{{ __($panel['label']) }}
                </button>
            @endforeach
        </div>
        @foreach ($panels as $index => $panel)
            <div role="tabpanel" id="{{ $frame->id('panel-'.$context->path().'-'.$index) }}" aria-labelledby="{{ $frame->id('tab-'.$context->path().'-'.$index) }}" tabindex="0" x-show="active === {{ $index }}" @if ($index > 0) x-cloak style="display: none" @endif>{!! $panel['html'] !!}</div>
        @endforeach
    </div>
@endunless
