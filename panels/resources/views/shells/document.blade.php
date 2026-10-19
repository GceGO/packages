{{-- gcego-panels::shells.document — a panel document: head, skip link, then the panel's layout. --}}
@php($presets = \Gcego\Panels\Presets\PanelPresets::class)
@php($styling = app(\Gcego\Chroma\ChromaManager::class))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" {{ $styling->rootAttributes($shell->state->theme) }}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $shell->title }} · {{ $shell->brandName() }}</title>
    @if ($shell->branding?->favicon() !== null)
        <link rel="icon" href="{{ $shell->branding->favicon() }}">
    @endif
    {{ $styling->appearanceTag($shell->nonce()) }}
    {{-- Runtime CSS of the panel's (or the tenant brand's) theme; nothing for built-in themes. --}}
    {{ $styling->themeTag($shell->state->theme, $shell->nonce()) }}
    {{-- The tenant's brand (gcego/swatches BrandResolver), between the theme and the user's own accent. --}}
    {{ $styling->brandTag(theme: $shell->state->theme, nonce: $shell->nonce()) }}
    {{ $styling->accentTag($shell->state->theme, $shell->nonce()) }}
    @if ($shell->vite !== [])
        @vite($shell->vite)
    @endif
</head>
<body class="{{ implode(' ', $presets::Document) }}" data-gcego-panel="{{ $shell->context->name() }}">
    <a href="#{{ $shell->id('content') }}" class="{{ implode(' ', $presets::SkipLink) }}">{{ __('gcego-panels::messages.skip') }}</a>
    @include('gcego-panels::shells.'.$shell->state->layout, ['shell' => $shell, 'content' => $content])
    {{ app(\Gcego\Panels\Extensions\PanelExtensions::class)->body($shell) }}
</body>
</html>
