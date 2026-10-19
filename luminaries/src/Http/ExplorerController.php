<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Http;

use Gcego\Luminaries\Exceptions\LuminariesException;
use Gcego\Luminaries\Luminaries;
use Gcego\Luminaries\Rendering\IconOptions;
use Gcego\Luminaries\Support\IconEnumGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The developer icon explorer (GET luminaries.explorer.path).
 *
 * Read-only: it searches, previews and copies identifiers; it never accepts
 * files. Off unless luminaries.explorer.enabled, and every request must pass
 * the "viewLuminariesExplorer" gate.
 */
final class ExplorerController
{
    public const string Ability = 'viewLuminariesExplorer';

    public function __invoke(Request $request, Luminaries $luminaries): View
    {
        abort_unless(Gate::allows(self::Ability), 403);

        $query = mb_substr(trim((string) $request->query('q', '')), 0, 64);
        $provider = (string) $request->query('provider', '');
        $provider = $provider === 'alias' || $luminaries->hasProvider($provider) ? $provider : '';
        $size = (string) $request->query('size', 'lg');
        $size = isset($luminaries->sizes()[$size]) ? $size : (string) array_key_first($luminaries->sizes());
        $perPage = $luminaries->config()->explorerPerPage;
        $page = max(1, min(500, (int) $request->query('page', '1')));

        $matches = $luminaries->search($query, $provider === '' ? null : $provider, $page * $perPage + 1);
        $hasMore = count($matches) > $page * $perPage;
        $results = [];

        foreach (array_slice($matches, ($page - 1) * $perPage, $perPage) as $match) {
            try {
                $resolved = $luminaries->resolve($match['name']);
                $preview = $luminaries->html($match['name'], new IconOptions(size: $size));
            } catch (LuminariesException) {
                $resolved = null;
                $preview = $luminaries->renderer()->placeholder($match['name'], new IconOptions(size: $size));
            }

            $results[] = [...$match, 'resolves_to' => $resolved?->canonical(), 'preview' => $preview, 'blade' => sprintf('<x-gcego-icon name="%s" />', $match['name']), 'enum' => IconEnumGenerator::reference($match['name'])];
        }

        $providers = [];

        foreach ($luminaries->providers() as $name => $instance) {
            $providers[$name] = $instance->metadata()->toArray();
        }

        return view('luminaries::explorer.index', [
            'query' => $query,
            'provider' => $provider,
            'size' => $size,
            'page' => $page,
            'hasMore' => $hasMore,
            'results' => $results,
            'providers' => $providers,
            'sizes' => $luminaries->sizes(),
            'aliasCount' => count($luminaries->aliases()),
        ]);
    }
}
