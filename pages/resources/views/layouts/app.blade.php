{{-- gcego-pages::layouts.app: the default document of page routes (gcego/panels replaces it). --}}
@php($presets = \Gcego\Pages\Presets\PagePresets::class)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" {{ $styling->rootAttributes() }}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    {{ $styling->appearanceTag(\Illuminate\Support\Facades\Vite::cspNonce()) }}
    {{ $styling->themeTag(nonce: \Illuminate\Support\Facades\Vite::cspNonce()) }}
    {{ $styling->brandTag(nonce: \Illuminate\Support\Facades\Vite::cspNonce()) }}
    @if ($vite !== [])
        @vite($vite)
    @endif
</head>
<body class="{{ implode(' ', $presets::Document) }}">
    <a href="#gcego-page-main" class="{{ implode(' ', $presets::SkipLink) }}">{{ __('gcego-pages::messages.skip') }}</a>
    <div class="{{ implode(' ', $presets::Shell) }}">
        @if ($navigation !== null)
            <div class="{{ implode(' ', $presets::Sidebar) }}">
                {{ app(\Gcego\Navigation\NavigationManager::class)->render($navigation) }}
            </div>
        @endif
        <main id="gcego-page-main" tabindex="-1" class="{{ implode(' ', $presets::Main) }}">
            {{ $content }}
        </main>
    </div>
</body>
</html>
