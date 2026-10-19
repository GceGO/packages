<?php

declare(strict_types=1);

namespace Gcego\Pages\Http;

use Gcego\Chroma\ChromaManager;
use Gcego\Pages\Exceptions\PageNotFoundException;
use Gcego\Pages\Page;
use Gcego\Pages\PageManager;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Routing\PageRouteResolver;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;

/**
 * Answers every page route. The page identity comes from the route
 * definition (trusted), never from the request. The page is opened (and
 * authorized, with its record) before anything renders, so denials and
 * missing records are real 403/404 responses (guests: the login redirect);
 * nothing is swallowed. The page itself then renders in the reusable
 * Livewire PageHost, inside the configured layout.
 */
final readonly class PageController
{
    public function __construct(private PageManager $pages, private PagesConfig $config) {}

    public function __invoke(Request $request): Response
    {
        $identity = PageRouteResolver::pageOf($request->route()) ?? throw PageNotFoundException::make();
        $parameters = [];
        $record = null;

        foreach ($request->route()?->parameters() ?? [] as $name => $value) {
            $value = $value instanceof UrlRoutable ? $value->getRouteKey() : $value;

            if (! is_string($value) && ! is_int($value)) {
                continue;
            }

            if ($name === Page::RecordParameter) {
                $record = (string) $value;
            } else {
                $parameters[$name] = (string) $value;
            }
        }

        $state = ['tab' => $request->query('tab')];
        $context = $this->pages->open($identity, $record, $parameters, $state);
        $content = Livewire::mount('gcego-page', [
            'page' => $identity,
            'record' => $record,
            'parameters' => $context->parameters,
            'tab' => $context->state->tab,
        ], 'gcego-page-'.substr(hash('sha256', $identity.'|'.$record), 0, 12));

        return response()->view($this->config->layout, [
            'title' => $context->header->title->text ?? $context->page->pageTitle(),
            'content' => new HtmlString((string) $content),
            'context' => $context,
            'vite' => $this->config->vite,
            'navigation' => $this->config->layoutNavigation,
            'styling' => app(ChromaManager::class),
        ]);
    }
}
