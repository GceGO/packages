@php($nonce = \Illuminate\Support\Facades\Vite::cspNonce())
<!DOCTYPE html>
<html lang="en" {{ $swatches->rootAttributes($theme->name()) }}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Colors · {{ $theme->theme->label() }}</title>
    {{ $swatches->appearanceTag($nonce) }}
    {{-- Prebuilt (resources/dist/explorer.css): the explorer adds nothing to application builds. --}}
    <style @if ($nonce) nonce="{{ $nonce }}" @endif>{!! $stylesheet !!}</style>
    {{ $swatches->themeTag($theme->name(), $nonce) }}
    {{ $swatches->accentTag($theme->name(), $nonce) }}
</head>
<body class="min-h-screen bg-background font-sans text-foreground antialiased">
    <header class="sticky top-0 z-10 border-b border-topbar-border bg-topbar text-topbar-foreground">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-3 px-4 py-3">
            <h1 class="text-base font-semibold">GCEGO Colors</h1>
            <form method="get" class="flex items-center gap-2">
                <label for="theme" class="text-sm text-muted-foreground">Theme</label>
                <select id="theme" name="theme" data-autosubmit class="{{ $previews['input'] }} w-auto">
                    @foreach ($themes as $name => $option)
                        <option value="{{ $name }}" @selected($name === $theme->name())>{{ $option->label() }}</option>
                    @endforeach
                </select>
                <noscript><button class="{{ $previews['button.secondary'] }}">Apply</button></noscript>
            </form>
            <div class="ms-auto">
                <x-gcego-swatches::appearance-switcher />
            </div>
        </div>
    </header>

    <main class="mx-auto grid max-w-7xl gap-10 px-4 py-8">
        <section aria-labelledby="preview-heading" class="grid gap-4">
            <h2 id="preview-heading" class="text-lg font-semibold">Component preview</h2>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="{{ $previews['card'] }} grid gap-4">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="{{ $previews['button.primary'] }}">Continue</button>
                        <button type="button" class="{{ $previews['button.secondary'] }}">Cancel</button>
                        <button type="button" class="{{ $previews['button.danger'] }}">Delete</button>
                        <button type="button" disabled class="inline-flex cursor-not-allowed items-center rounded-md bg-disabled px-4 py-2 text-sm font-medium text-disabled-foreground">Disabled</button>
                    </div>
                    <div class="grid gap-1.5">
                        <label for="preview-email" class="text-sm font-medium">Email</label>
                        <input id="preview-email" type="email" placeholder="name@school.cm" class="{{ $previews['input'] }}">
                        <p class="text-sm text-muted-foreground">We never share it.</p>
                    </div>
                    <div class="grid gap-1.5">
                        <label for="preview-invalid" class="text-sm font-medium">Admission number</label>
                        <input id="preview-invalid" type="text" value="GCE-00" aria-invalid="true" aria-describedby="preview-invalid-error" class="{{ $previews['input'] }}">
                        <p id="preview-invalid-error" class="text-sm text-danger">Use the full 8-character number.</p>
                    </div>
                    <p class="text-sm">Read the <a href="#tokens-heading" class="font-medium text-link underline hover:text-link-hover">token reference</a>.</p>
                </div>

                <div class="grid gap-3">
                    @foreach (['success' => 'Saved. The class list was updated.', 'warning' => 'Two marks are still missing.', 'danger' => 'The upload failed. Try again.', 'info' => 'Term 2 opens on Monday.'] as $tone => $message)
                        <div role="status" class="flex items-start gap-2 rounded-md border p-3 text-sm {{ $previews['tone.'.$tone] }}">
                            <span class="font-semibold capitalize">{{ $tone }}:</span>
                            <span class="text-foreground">{{ $message }}</span>
                        </div>
                    @endforeach
                    <div class="flex flex-wrap gap-2">
                        @foreach (['success' => 'Paid', 'warning' => 'Pending', 'danger' => 'Overdue', 'info' => 'New'] as $tone => $label)
                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium {{ $previews['tone.'.$tone] }}">
                                <span aria-hidden="true" class="size-1.5 rounded-full bg-current"></span>{{ $label }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-border bg-surface">
                    <table class="{{ $previews['table'] }}">
                        <thead class="{{ $previews['table.head'] }}">
                            <tr><th class="{{ $previews['table.header-cell'] }}">Student</th><th class="{{ $previews['table.header-cell'] }}">Class</th><th class="{{ $previews['table.header-cell'] }}">Average</th></tr>
                        </thead>
                        <tbody>
                            <tr class="{{ $previews['table.row'] }}"><td class="{{ $previews['table.cell'] }}">Ngum Bih</td><td class="{{ $previews['table.cell'] }}">Form 5</td><td class="{{ $previews['table.cell'] }} text-success">15.2</td></tr>
                            <tr class="{{ $previews['table.row'] }} bg-selected text-selected-foreground" aria-selected="true"><td class="{{ $previews['table.cell'] }}">Tabi Eyong</td><td class="{{ $previews['table.cell'] }}">Lower Sixth</td><td class="{{ $previews['table.cell'] }}">12.8</td></tr>
                            <tr class="{{ $previews['table.row'] }}"><td class="{{ $previews['table.cell'] }}">Fon Achu</td><td class="{{ $previews['table.cell'] }}">Upper Sixth</td><td class="{{ $previews['table.cell'] }} text-danger">8.4</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-[12rem_1fr] overflow-hidden rounded-lg border border-border">
                    <nav aria-label="Preview navigation" class="grid content-start gap-1 border-e border-sidebar-border bg-sidebar p-2">
                        <p class="px-3 pb-1 pt-2 text-xs font-medium uppercase tracking-wide text-sidebar-muted-foreground">School</p>
                        <a href="#preview-heading" aria-current="page" class="{{ $previews['nav.item'] }}">Dashboard</a>
                        <a href="#preview-heading" class="{{ $previews['nav.item'] }}">Students</a>
                        <a href="#preview-heading" class="{{ $previews['nav.item'] }}">Results</a>
                    </nav>
                    <div class="grid content-start gap-3 bg-background p-4">
                        <div class="rounded-lg border border-border bg-card p-4 text-card-foreground">
                            <p class="text-sm text-muted-foreground">Pass rate</p>
                            <p class="text-2xl font-semibold">87%</p>
                            <p class="text-sm text-chart-positive">▲ 4% since last term</p>
                        </div>
                        <div class="flex h-20 items-end gap-1.5" role="img" aria-label="Chart series colors 1 to 8">
                            @foreach (\Gcego\Swatches\Tokens\SemanticTokens::ChartSeries as $index => $series)
                                <span class="w-full rounded-t-sm" style="height: {{ 30 + ($index * 37) % 70 }}%; background: var(--gcego-{{ $series }})"></span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section aria-labelledby="contrast-heading" class="grid gap-4">
            <h2 id="contrast-heading" class="text-lg font-semibold">Contrast</h2>
            <form method="get" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="theme" value="{{ $theme->name() }}">
                <div class="grid gap-1.5"><label for="fg" class="text-sm font-medium">Foreground (color or token)</label><input id="fg" name="fg" value="{{ $check['foreground'] ?? 'muted-foreground' }}" class="{{ $previews['input'] }}"></div>
                <div class="grid gap-1.5"><label for="bg" class="text-sm font-medium">Background (color or token)</label><input id="bg" name="bg" value="{{ $check['background'] ?? 'surface' }}" class="{{ $previews['input'] }}"></div>
                <button class="{{ $previews['button.primary'] }}">Check</button>
            </form>
            @if ($check !== null)
                <dl class="grid gap-2 sm:grid-cols-2">
                    @foreach ($check['results'] as $mode => $result)
                        <div class="{{ $previews['card'] }}">
                            <dt class="text-sm text-muted-foreground capitalize">{{ $mode }}</dt>
                            @if ($result === null)
                                <dd class="text-danger">Not a color or token.</dd>
                            @else
                                <dd class="text-xl font-semibold">{{ $result['ratio'] }}</dd>
                                <dd class="text-sm">{{ $result['foreground'] }} on {{ $result['background'] }} · text {{ $result['text'] ? 'passes' : 'fails' }} (4.5:1) · large text and UI {{ $result['large'] ? 'pass' : 'fail' }} (3:1)</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>
            @endif
            <div class="overflow-x-auto rounded-lg border border-border bg-surface">
                <table class="{{ $previews['table'] }}">
                    <caption class="sr-only">WCAG 2.2 AA audit of {{ $theme->theme->label() }}</caption>
                    <thead class="{{ $previews['table.head'] }}"><tr>
                        <th class="{{ $previews['table.header-cell'] }}">Pair</th><th class="{{ $previews['table.header-cell'] }}">Mode</th><th class="{{ $previews['table.header-cell'] }}">Used for</th><th class="{{ $previews['table.header-cell'] }}">Ratio</th><th class="{{ $previews['table.header-cell'] }}">Result</th><th class="{{ $previews['table.header-cell'] }}">APCA (advisory)</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($audit as $result)
                            <tr class="{{ $previews['table.row'] }}">
                                <td class="{{ $previews['table.cell'] }}"><span class="inline-flex items-center gap-2"><span aria-hidden="true" class="inline-grid size-6 place-items-center rounded-sm border border-border text-xs font-semibold" style="color: {{ $result->foregroundColor->toHex() }}; background: {{ $result->backgroundColor->toHex() }}">Aa</span><code>{{ $result->foreground }}</code> on <code>{{ $result->background }}</code></span></td>
                                <td class="{{ $previews['table.cell'] }}">{{ $result->mode() }}</td>
                                <td class="{{ $previews['table.cell'] }} text-muted-foreground">{{ $result->purpose }}</td>
                                <td class="{{ $previews['table.cell'] }} tabular-nums">{{ \Gcego\Swatches\Accessibility\Contrast::format($result->ratio) }}</td>
                                <td class="{{ $previews['table.cell'] }}">{!! $result->passes() ? '<span class="text-success">✓ Pass</span>' : '<span class="text-danger">✗ Fail</span>' !!} <span class="text-muted-foreground">{{ $result->requirement->label() }}</span>@if ($result->suggestion) <span class="text-muted-foreground">· try {{ $result->suggestion->toHex() }}</span>@endif</td>
                                <td class="{{ $previews['table.cell'] }} tabular-nums"><span class="{{ $result->passesApca() ? '' : 'text-warning' }}">{{ \Gcego\Swatches\Accessibility\Apca::format($result->apca) }}</span> <span class="text-muted-foreground">/ {{ (int) \Gcego\Swatches\Accessibility\Apca::minimum($result->requirement) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="vision-heading" class="grid gap-4">
            <div class="grid gap-1">
                <h2 id="vision-heading" class="text-lg font-semibold">Color vision</h2>
                <p class="text-sm text-muted-foreground">Status and chart colors on the surface, simulated (Machado et al. 2009). Where swatches look alike, labels, icons or patterns must carry the meaning.</p>
            </div>
            @foreach ($vision as $mode => $rows)
                <div class="overflow-x-auto rounded-lg border border-border bg-surface">
                    <table class="{{ $previews['table'] }}">
                        <caption class="px-4 py-2 text-start text-sm font-medium capitalize">{{ $mode }} mode</caption>
                        <thead class="{{ $previews['table.head'] }}"><tr>
                            <th class="{{ $previews['table.header-cell'] }}">Vision</th>
                            @foreach (array_keys(reset($rows)) as $token)<th class="{{ $previews['table.header-cell'] }} font-mono text-xs">{{ $token }}</th>@endforeach
                        </tr></thead>
                        <tbody>
                            @foreach ($rows as $label => $colors)
                                <tr class="{{ $previews['table.row'] }}">
                                    <th scope="row" class="{{ $previews['table.cell'] }} whitespace-nowrap font-medium">{{ $label }}</th>
                                    @foreach ($colors as $token => $hex)
                                        <td class="{{ $previews['table.cell'] }}"><span class="block h-6 min-w-8 rounded-sm border border-border" style="background: {{ $hex }}" title="{{ $token }} {{ $hex }}"></span></td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </section>

        <section aria-labelledby="tokens-heading" class="grid gap-4">
            <h2 id="tokens-heading" class="text-lg font-semibold">Semantic tokens</h2>
            @foreach ($groups as $group => $tokens)
                <div class="grid gap-2">
                    <h3 class="text-sm font-semibold capitalize text-muted-foreground">{{ $group }}</h3>
                    <ul class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($tokens as $token)
                            <li class="flex items-center gap-3 rounded-md border border-border bg-surface p-2">
                                <span aria-hidden="true" class="grid size-10 shrink-0 grid-cols-2 overflow-hidden rounded-sm border border-border">
                                    <span style="background: {{ $token['light_hex'] }}"></span><span style="background: {{ $token['dark_hex'] }}"></span>
                                </span>
                                <span class="grid min-w-0 text-sm">
                                    <button type="button" data-copy="--gcego-{{ $token['name'] }}" class="truncate text-start font-mono font-medium hover:text-link focus-visible:outline-2 focus-visible:outline-ring" title="Copy --gcego-{{ $token['name'] }}">{{ $token['name'] }}</button>
                                    <span class="truncate text-xs text-muted-foreground">{{ $token['light'] }} · {{ $token['dark'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </section>

        <section aria-labelledby="palettes-heading" class="grid gap-4">
            <h2 id="palettes-heading" class="text-lg font-semibold">Palettes</h2>
            @foreach ($palettes as $palette)
                <div class="grid gap-1">
                    <h3 class="text-sm font-medium">{{ $palette->label() }} <span class="text-muted-foreground">· {{ $palette->metadata['source'] ?? 'custom' }}</span></h3>
                    <ul class="flex gap-1">
                        @foreach ($palette->colors as $step => $color)
                            <li class="grid min-w-0 flex-1 gap-0.5 text-center">
                                <button type="button" data-copy="{{ $palette->isBuiltIn() ? 'bg-'.$palette->name.'-'.$step : $color->toCss() }}" class="h-10 rounded-sm border border-border focus-visible:outline-2 focus-visible:outline-ring" style="background: {{ $color->toHex() }}" title="{{ $color->toHex() }} · {{ $color->toOklchString() }}"><span class="sr-only">{{ $palette->name }} {{ $step }} {{ $color->toHex() }}</span></button>
                                <span class="text-[10px] text-muted-foreground">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </section>

        <section aria-labelledby="export-heading" class="grid gap-4">
            <h2 id="export-heading" class="text-lg font-semibold">Export {{ $theme->theme->label() }}</h2>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="grid gap-1.5"><p class="text-sm font-medium">config/gcego-swatches.php</p><pre class="max-h-80 overflow-auto rounded-md border border-border bg-surface-muted p-3 text-xs">'{{ $theme->name() }}' => {{ $export['php'] }},</pre></div>
                <div class="grid gap-1.5"><p class="text-sm font-medium">JSON</p><pre class="max-h-80 overflow-auto rounded-md border border-border bg-surface-muted p-3 text-xs">{{ $export['json'] }}</pre></div>
            </div>
        </section>
    </main>

    <script @if ($nonce) nonce="{{ $nonce }}" @endif>
        document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
            select.addEventListener('change', function () { select.form.submit(); });
        });
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-copy]');
            if (button && navigator.clipboard) {
                navigator.clipboard.writeText(button.getAttribute('data-copy'));
                button.setAttribute('data-copied', '');
                setTimeout(function () { button.removeAttribute('data-copied'); }, 1200);
            }
        });
    </script>
</body>
</html>
