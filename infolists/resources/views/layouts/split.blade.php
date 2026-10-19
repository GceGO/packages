@unless ($skip)
    <div class="{{ $styles('root') }} grid grid-cols-1 gap-6 {{ $layout->gridClass() }}" data-gcego-split>
        @foreach ($parts as $index => $part)
            @php($groups = $part)
            <div class="flex min-w-0 flex-col gap-5" @if ($index === 1) role="complementary" aria-label="{{ __('gcego-infolists::messages.aside') }}" @endif>
                @include('gcego-infolists::layouts.groups')
            </div>
        @endforeach
    </div>
@endunless
