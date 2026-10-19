@php($nonce = \Illuminate\Support\Facades\Vite::cspNonce())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Icon Explorer · GCEGO Luminaries</title>
    <style @if ($nonce) nonce="{{ $nonce }}" @endif>
        :root { --bg: #f8fafc; --surface: #ffffff; --text: #0f172a; --muted: #64748b; --border: #e2e8f0; --primary: #4f46e5; --soft: #eef2ff; --danger: #dc2626; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b1120; --surface: #111827; --text: #e5e7eb; --muted: #94a3b8; --border: #1f2937; --primary: #818cf8; --soft: #1e1b4b; --danger: #f87171; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
        header, main { max-width: 1200px; margin: 0 auto; padding: 16px; }
        h1 { font-size: 20px; margin: 8px 0 4px; }
        p.lead { color: var(--muted); margin: 0 0 16px; }
        form { display: flex; flex-wrap: wrap; gap: 8px; }
        input, select, button { font: inherit; color: inherit; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 8px 10px; }
        input[type=search] { flex: 1 1 240px; }
        button { cursor: pointer; }
        button.primary { background: var(--primary); color: #fff; border-color: transparent; }
        :focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; margin-top: 16px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 12px; display: flex; flex-direction: column; gap: 8px; min-width: 0; }
        .preview { display: flex; align-items: center; justify-content: center; height: 64px; border-radius: 8px; background: var(--soft); color: var(--primary); }
        .name { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; overflow-wrap: anywhere; }
        .meta { color: var(--muted); font-size: 12px; overflow-wrap: anywhere; }
        .missing { color: var(--danger); }
        .actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .actions button { padding: 4px 8px; font-size: 12px; }
        details { margin-top: 16px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 8px 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 6px 8px; border-top: 1px solid var(--border); }
        .table-wrap { overflow-x: auto; }
        nav.pager { display: flex; gap: 8px; margin: 16px 0; }
        nav.pager a { color: var(--primary); }
        .size-3 { width: .75rem; height: .75rem; } .size-4 { width: 1rem; height: 1rem; } .size-5 { width: 1.25rem; height: 1.25rem; }
        .size-6 { width: 1.5rem; height: 1.5rem; } .size-8 { width: 2rem; height: 2rem; } .size-10 { width: 2.5rem; height: 2.5rem; }
        .shrink-0 { flex-shrink: 0; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
    </style>
</head>
<body>
<header>
    <h1>Icon Explorer</h1>
    <p class="lead">GCEGO Luminaries · {{ count($providers) }} providers · {{ $aliasCount }} semantic aliases. Developer tool: not for production use.</p>
    <form method="get" role="search">
        <label class="sr-only" for="q">Search icons</label>
        <input id="q" type="search" name="q" value="{{ $query }}" placeholder="Search icons and aliases (e.g. academic, actions.)" autofocus>
        <label class="sr-only" for="provider">Provider</label>
        <select id="provider" name="provider">
            <option value="">All providers and aliases</option>
            <option value="alias" @selected($provider === 'alias')>Semantic aliases</option>
            @foreach ($providers as $name => $meta)
                <option value="{{ $name }}" @selected($provider === $name)>{{ $meta['label'] }} ({{ $name }})</option>
            @endforeach
        </select>
        <label class="sr-only" for="size">Preview size</label>
        <select id="size" name="size">
            @foreach ($sizes as $token => $classes)
                <option value="{{ $token }}" @selected($size === $token)>{{ $token }} ({{ $classes }})</option>
            @endforeach
        </select>
        <button class="primary" type="submit">Search</button>
    </form>
    <details>
        <summary>Providers</summary>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Namespace</th><th scope="col">Label</th><th scope="col">Driver</th><th scope="col">Style</th><th scope="col">Stroke width</th><th scope="col">Source</th></tr></thead>
                <tbody>
                @foreach ($providers as $name => $meta)
                    <tr><td class="name">{{ $name }}</td><td>{{ $meta['label'] }}</td><td>{{ $meta['driver'] }}</td><td>{{ $meta['style'] }}</td><td>{{ $meta['stroke_width'] ? 'yes' : 'no' }}</td><td class="meta">{{ $meta['source'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </details>
</header>
<main>
    @if ($results === [])
        <p class="lead">No icons or aliases match.</p>
    @endif
    <div class="grid">
        @foreach ($results as $result)
            <article class="card">
                <div class="preview">{!! $result['preview'] !!}</div>
                <div class="name">{{ $result['name'] }}</div>
                @if ($result['kind'] === 'alias')
                    <div class="meta">alias → {{ $result['target'] }}</div>
                    @if ($result['resolves_to'] === null)
                        <div class="meta missing">does not resolve</div>
                    @elseif ($result['resolves_to'] !== $result['target'])
                        <div class="meta">resolves to {{ $result['resolves_to'] }}</div>
                    @endif
                @else
                    <div class="meta">{{ $providers[$result['provider']]['label'] ?? $result['provider'] }}</div>
                @endif
                <div class="actions">
                    <button type="button" data-copy="{{ $result['name'] }}">Copy name</button>
                    <button type="button" data-copy="{{ $result['blade'] }}">Copy Blade</button>
                    @if ($result['enum'] !== null)
                        <button type="button" data-copy="{{ $result['enum'] }}">Copy PHP</button>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
    <nav class="pager" aria-label="Pages">
        @if ($page > 1)
            <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}">Previous</a>
        @endif
        @if ($hasMore)
            <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">Next</a>
        @endif
    </nav>
    <p class="sr-only" role="status" aria-live="polite" id="copy-status"></p>
</main>
<script @if ($nonce) nonce="{{ $nonce }}" @endif>
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-copy]');
        if (!button || !navigator.clipboard) { return; }
        navigator.clipboard.writeText(button.getAttribute('data-copy')).then(function () {
            var label = button.textContent;
            button.textContent = 'Copied';
            document.getElementById('copy-status').textContent = 'Copied ' + button.getAttribute('data-copy');
            setTimeout(function () { button.textContent = label; }, 1200);
        });
    });
</script>
</body>
</html>
