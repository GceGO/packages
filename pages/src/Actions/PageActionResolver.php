<?php

declare(strict_types=1);

namespace Gcego\Pages\Actions;

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Actions\ActionPlacement;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Pages\Authorization\PageAuthorizer;
use Gcego\Pages\Components\PageLink;
use Gcego\Pages\Context\PageContext;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Records\RecordKey;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Pages\Routing\PageRouteResolver;
use Gcego\Pages\Types\CreatePage;
use Gcego\Pages\Types\FormPage;
use Gcego\Pages\Types\IndexPage;

/**
 * Places page actions and follows their results; it never executes
 * anything (gcego/actions does, through its own hosts, which re-resolve and
 * re-authorize every run).
 *
 *  - Header: the page's direct PageLinks, and its direct actions placed in
 *    "header" (on record pages without an infolist, which shows them, also
 *    "record"), except the action its form submits.
 *  - After an action completed (the "gcego-action-completed" browser event,
 *    untrusted): where the page goes. The event only selects among targets
 *    the definition allows; URLs are built here, and the target page
 *    authorizes its record again when it opens.
 *      Create/Edit pages, their form's action: RedirectTo (create: view, else edit, else index; edit: stay).
 *      Record pages, "delete" or "force-delete" of their record: the parent (index) page.
 *      A row "view" action: the record's view page.
 */
final readonly class PageActionResolver
{
    public function __construct(
        private PageDefinitions $pages,
        private PageRouteResolver $routes,
        private PageAuthorizer $authorizer,
        private PagesConfig $config,
    ) {}

    /**
     * Paths of the page's header actions and links, in declaration order.
     *
     * @return list<string>
     */
    public function headerPaths(Page $page, bool $record): array
    {
        $paths = [];
        $walker = new ComponentWalker;

        foreach ($walker->childNodes($walker->rootNode($page)) as $node) {
            $component = $node->component;
            $header = match (true) {
                $component instanceof PageLink => true,
                $component instanceof Action => $this->inHeader($component, $page, $record),
                $component instanceof ActionGroup => array_filter($component->actions(), fn (Action $action): bool => $this->inHeader($action, $page, $record)) !== [],
                default => false,
            };

            if ($header) {
                $paths[] = $node->path->toString();
            }
        }

        return $paths;
    }

    /**
     * Where to go after an action of this page completed, or null to stay.
     *
     * @param  mixed  $result  The untrusted event payload (ActionResult::toArray()).
     */
    public function redirectAfter(PageContext $context, mixed $result): ?string
    {
        if (! is_array($result) || ($result['status'] ?? null) !== 'success' || ! is_string($result['action'] ?? null)) {
            return null;
        }

        $prefix = $context->identity.'/action:';
        $action = $result['action'];

        if (! str_starts_with($action, $prefix) || preg_match(Action::NamePattern, $name = substr($action, strlen($prefix))) !== 1) {
            return null;
        }

        $key = $result['record_key'] ?? null;
        $key = is_int($key) || is_string($key) ? (string) $key : null;
        $page = $context->page;

        if ($page instanceof FormPage && $name === $page::formAction()) {
            return $this->afterSave($context, $page, $key);
        }

        if ($context->hasRecord() && in_array($name, ['delete', 'force-delete'], true) && $key === $context->recordKey()) {
            return $this->first($context->identity, $context->page, $context->parameters, [$this->indexName($page)], null);
        }

        $data = $result['data'] ?? null;

        if ($name === 'view' && $key !== null && is_array($data) && ($data['intent'] ?? null) === 'view') {
            return $this->first($context->identity, $context->page, $context->parameters, $this->siblings($page, ['view', 'show']), $key);
        }

        return null;
    }

    private function inHeader(Action $action, Page $page, bool $record): bool
    {
        if ($action->isHidden() || ($page instanceof FormPage && $action->actionName() === $page::formAction())) {
            return false;
        }

        $placements = $action->placements();

        // An infolist shows the record's "record" actions itself.
        return in_array(ActionPlacement::Header, $placements, true) || ($record && in_array(ActionPlacement::Record, $placements, true) && ! $page->hasPart(BlueprintRole::Infolist));
    }

    private function afterSave(PageContext $context, FormPage $page, ?string $key): ?string
    {
        $target = $page->redirectTo() ?? ($page instanceof CreatePage ? 'view' : 'self');

        return match ($target) {
            'none', 'self' => null,
            'view' => $this->first($context->identity, $context->page, $context->parameters, [...$this->siblings($page, ['view', 'show']), ...$this->siblings($page, ['edit']), $this->indexName($page)], $key),
            'edit' => $this->first($context->identity, $context->page, $context->parameters, [...$this->siblings($page, ['edit']), $this->indexName($page)], $key),
            'index' => $this->first($context->identity, $context->page, $context->parameters, [$this->indexName($page)], $key),
            default => $this->first($context->identity, $context->page, $context->parameters, [$target], $key),
        };
    }

    /**
    /**
     * The URL of the page's list (index) page, when the user may open it.
     *
     * @param  array<string, string>  $parameters  The page's route parameters.
     */
    public function indexUrl(string $identity, Page $page, array $parameters = []): ?string
    {
        return $this->first($identity, $page, $parameters, [$this->indexName($page)], null);
    }

    /**
     * The URL of the first candidate page that exists, has a route and that the user may open (with the record when it needs one).
     *
     * @param  array<string, string>  $parameters
     * @param  list<string|null>  $candidates
     */
    private function first(string $currentIdentity, Page $current, array $parameters, array $candidates, ?string $key): ?string
    {
        foreach ($candidates as $name) {
            if ($name === null || $name === $current->pageName() || ! $this->pages->has($name, $currentIdentity)) {
                continue;
            }

            $page = $this->pages->resolve($name, $currentIdentity);
            $identity = $this->pages->identity($name, $currentIdentity);

            if (! $this->authorizer->allows($identity, $page)) {
                continue;
            }

            if ($page->requiresRecord()) {
                if ($key === null || ! RecordKey::valid($page->recordKey($this->config->recordKey), $key) || ! $this->authorizer->record($identity, $page, $key)[0]->allowed) {
                    continue;
                }
            }

            $url = $this->routes->url($identity, $page->requiresRecord() ? $key : null, array_intersect_key($parameters, array_flip($page->parameters())));

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Sibling page names: "subjects.create" + ["view"] -> ["subjects.view"]; for index pages, below them: "subjects" -> "subjects.view".
     *
     * @param  list<string>  $segments
     * @return list<string>
     */
    private function siblings(Page $page, array $segments): array
    {
        $base = $this->base($page);

        return array_map(static fn (string $segment): string => $base.'.'.$segment, $segments);
    }

    private function indexName(Page $page): ?string
    {
        $parent = $page->parentName();

        if ($parent !== null) {
            return $parent;
        }

        return $page instanceof IndexPage ? null : $this->base($page);
    }

    private function base(Page $page): string
    {
        if ($page instanceof IndexPage) {
            return $page->pageName();
        }

        $segments = explode('.', $page->pageName());

        if (count($segments) > 1) {
            array_pop($segments);
        }

        return implode('.', $segments);
    }
}
