<?php

declare(strict_types=1);

namespace Gcego\Pages\Lifecycle;

use Gcego\Pages\Actions\PageActionResolver;
use Gcego\Pages\Authorization\PageAuthorizer;
use Gcego\Pages\Breadcrumbs\PageBreadcrumbs;
use Gcego\Pages\Context\PageContext;
use Gcego\Pages\Context\RecordPageContext;
use Gcego\Pages\Contracts\PageTitleProvider;
use Gcego\Pages\Exceptions\PageNotFoundException;
use Gcego\Pages\Headers\PageDescription;
use Gcego\Pages\Headers\PageHeader;
use Gcego\Pages\Headers\PageTitle;
use Gcego\Pages\Page;
use Gcego\Pages\Records\PageRecordResolver;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Pages\Routing\PageRouteResolver;
use Gcego\Pages\State\PageState;
use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\SecurityManager;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Container\Container;

/**
 * Opens a page for the current request, stage by stage:
 *
 *   1. Resolved        the definition, from gcego/registry by identity only
 *   2. ContextCreated  route parameters checked, principal, tenant, locale
 *   3. Authorized      page requirements (or the configured default)
 *   4. RecordResolved  record pages: scoped lookup + record ability
 *   5. Prepared        title, description, breadcrumbs, header actions
 *
 * then (PageManager) Rendering, Rendered and Completed; a Livewire update
 * runs 1-5 again and adds Interaction. Each stage can only refuse (404/403);
 * hooks are told after each stage.
 *
 * Request-scoped. Opened pages are memoized by identity, record, parameters,
 * state, request and security fingerprint, so a page opened by the route and
 * then by its Livewire host in the same request is authorized and queried once.
 */
final class PageLifecycle
{
    private const string ParameterValuePattern = '/^[A-Za-z0-9_.:@-]{1,191}$/';

    /**
     * @var array<string, PageContext>
     */
    private array $opened = [];

    public function __construct(
        private readonly Container $container,
        private readonly PageDefinitions $pages,
        private readonly PageAuthorizer $authorizer,
        private readonly PageRecordResolver $records,
        private readonly PageBreadcrumbs $breadcrumbs,
        private readonly PageActionResolver $actions,
        private readonly PageRouteResolver $routes,
        private readonly SecurityManager $security,
        private readonly PageHooks $hooks,
    ) {}

    /**
     * @param  array<array-key, mixed>  $parameters  Untrusted route parameters other than {record}.
     * @param  array<array-key, mixed>  $state  Untrusted page state (tab).
     *
     * @throws PageNotFoundException|AccessDeniedException|AuthenticationException
     */
    public function open(string $page, string|int|null $record = null, array $parameters = [], array $state = [], bool $interaction = false): PageContext
    {
        $identity = $this->pages->identity($page);
        $request = $this->container->bound('request') ? spl_object_id($this->container->make('request')) : 0;
        $key = implode('|', [$identity, (string) $record, (string) json_encode($parameters), (string) json_encode($state), $request, $this->security->context()->fingerprint(), $interaction ? 'i' : '']);

        return $this->opened[$key] ??= $this->run($identity, $record === null ? null : (string) $record, $parameters, $state, $interaction);
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     * @param  array<array-key, mixed>  $state
     */
    private function run(string $identity, ?string $record, array $parameters, array $state, bool $interaction): PageContext
    {
        $page = $this->pages->resolve($identity);
        $this->fire(PageStage::Resolved, $identity, $page);

        $security = $this->security->context();
        $timezone = config('app.timezone');
        $context = new PageContext(
            identity: $identity,
            page: $page,
            route: $this->routes->routeName($identity),
            parameters: $this->parameters($page, $parameters),
            user: $security->user,
            tenant: $security->tenant === null ? null : (string) $security->tenant->key,
            locale: app()->getLocale(),
            timezone: is_string($timezone) ? $timezone : 'UTC',
            fingerprint: $security->fingerprint(),
            state: PageState::from($page, $state),
        );
        $this->fire(PageStage::ContextCreated, $identity, $page, $context);

        $this->authorizer->authorize($identity, $page);
        $this->fire(PageStage::Authorized, $identity, $page, $context);

        if ($page->requiresRecord()) {
            if ($record === null) {
                throw PageNotFoundException::make();
            }

            $model = $this->records->resolve($identity, $page, $record);
            $context = RecordPageContext::for($context, $model, $record, $this->records->label($page, $model));
            $this->fire(PageStage::RecordResolved, $identity, $page, $context);
        }

        $context = $context->withHeader($this->header($context));
        $this->fire(PageStage::Prepared, $identity, $page, $context);

        if ($interaction) {
            $this->fire(PageStage::Interaction, $identity, $page, $context);
        }

        return $context;
    }

    public function fire(PageStage $stage, string $identity, Page $page, ?PageContext $context = null): void
    {
        $this->hooks->fire(new PageLifecycleEvent($stage, $identity, $page, $context));
    }

    private function header(PageContext $context): PageHeader
    {
        $class = $context->page->titleProvider();
        $provider = $class === null ? null : $this->container->make($class);
        $provider = $provider instanceof PageTitleProvider ? $provider : null;
        $title = PageTitle::for($context, $provider);

        return new PageHeader(
            $title,
            PageDescription::for($context, $provider),
            $context->page->icon(),
            $this->breadcrumbs->for($context, $title->text),
            $this->actions->headerPaths($context->page, $context->hasRecord()),
        );
    }

    /**
     * The page's own path parameters (other than {record}), each a short safe
     * string matching the page's Where constraint. Anything else is 404.
     *
     * @param  array<array-key, mixed>  $parameters
     * @return array<string, string>
     */
    private function parameters(Page $page, array $parameters): array
    {
        $where = $page->where();
        $values = [];

        foreach ($page->parameters() as $name) {
            if ($name === Page::RecordParameter) {
                continue;
            }

            $value = $parameters[$name] ?? null;
            $value = is_int($value) ? (string) $value : $value;

            if (! is_string($value) || preg_match(self::ParameterValuePattern, $value) !== 1 || (isset($where[$name]) && preg_match('#^(?:'.$where[$name].')$#u', $value) !== 1)) {
                throw PageNotFoundException::make();
            }

            $values[$name] = $value;
        }

        return $values;
    }
}
