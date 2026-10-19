# API reference

## Gcego\Pages\PageManager (singleton)

| Method | Purpose |
| --- | --- |
| `identity(string $page): string` | `"subjects.create"` → `"page:subjects-create"` |
| `has(string $page): bool`, `resolve(string $page): Page` | Registered pages (404 when unknown) |
| `open(string $page, string\|int\|null $record = null, array $parameters = [], array $state = []): PageContext` | Lifecycle up to Prepared |
| `render(…same…): HtmlString` | Open and render (static) |
| `renderContext(PageContext $context, bool $live = false): HtmlString` | Render an open page |
| `url(string $page, string\|int\|null $record = null, array $parameters = []): ?string` | The page's route URL |
| `routes(?string $prefix, ?string $namePrefix, ?array $middleware, ?string $domain, ?Closure $filter): list<Route>` | Register routes |
| `hook(string $hook): void` | Add a lifecycle hook |
| `inspect(string\|Page $page): PageReport`, `inspectAll(): list<PageReport>` | Diagnostics |
| `config(): PagesConfig` | Validated configuration |

## Definitions

- `Page` (`pageType()`, `requiredRole()`, `styleSlots()`, `pageName()`, `registryName()`, `pageTitle()`, `path()`, `routeName()`, `parameters()`, `requiresRecord()`, `recordAbility()`, `recordKey()`, `navigation()`, `source()`, `problems()`).
- `Types\IndexPage`, `Types\FormPage` (`formAction()`), `Types\CreatePage`, `Types\EditPage`, `Types\ViewPage`, `Types\DashboardPage`, `Types\CustomPage`.
- `Components\Content` (`Text`), `Components\PageLink` (`Page`, `Icon`, `Intent`, `Parameters`, `Record`).

## Contracts

- `Contracts\PageLifecycleHook::handle(PageLifecycleEvent $event): void`
- `Contracts\PageTitleProvider::title(PageContext): ?string`, `description(PageContext): ?string`
- `Contracts\PageComponent` (marker for page components)

## Context and lifecycle

- `Context\PageContext` (`identity`, `page`, `route`, `parameters`, `user`, `tenant`, `locale`, `timezone`, `fingerprint`, `state`, `header`, `type()`, `name()`, `hasRecord()`, `recordKey()`, `parameter()`).
- `Context\RecordPageContext` (`record`, `key`, `label`).
- `Lifecycle\PageLifecycle::open(…, bool $interaction = false)`, `Lifecycle\PageStage`, `Lifecycle\PageLifecycleEvent`, `Lifecycle\PageHooks`.

## Routing

- `Routing\PageRouteRegistrar::register(Router $router, …): list<Route>`; action key `PageRouteRegistrar::ActionKey` (`gcego_page`).
- `Routing\PageRouteDefinition` (`fromPage()`, `fromDescriptor()`, `toMetadata()`, `parameters()`).
- `Routing\PageRouteResolver` (`routeName()`, `routeNames()`, `url()`, `pageOf(Route)`).
- `Http\PageController`.

## Authorization and records

- `Authorization\PageAuthorizer` (`decision()`, `allows()`, `authorize()`, `allowsChild()`, `record()`).
- `Records\PageRecordResolver` (`validKey()`, `resolve()`, `label()`), `Records\RecordKey` (`Patterns`, `pattern()`, `valid()`).

## Headers, breadcrumbs, actions, state

- `Headers\PageHeader`, `Headers\PageTitle`, `Headers\PageDescription`.
- `Breadcrumbs\PageBreadcrumbs::for(PageContext, string $title): BreadcrumbCollection`.
- `Actions\PageActionResolver` (`headerPaths()`, `redirectAfter()`, `indexUrl()`).
- `State\PageState` (`from()`, `tabs()`, `apply()`).

## Rendering and Livewire

- `Rendering\PageRenderer`, `ContentRenderer`, `PageLinkRenderer`, `PageRenderContext`, `PageView`.
- `Livewire\PageHost` (`gcego-page`).

## Registry, navigation, diagnostics

- `Registry\PageKind` (`page`), `Registry\PageDefinitions` (`identity()`, `scopeOf()`, `has()`, `resolve()`, `descriptor()`, `descriptors()`).
- `Navigation\PageNavigationContributor`.
- `Diagnostics\PageInspector`, `PageReport`, `PageDiagnostic`.

## Exceptions

`Exceptions\PageException` (marker), `InvalidPageException`, `PageNotFoundException` (404), `PageRouteConflictException`, `PageRenderingException`.

## Configuration (gcego-pages)

`routing.enabled`, `routing.prefix`, `routing.name_prefix`, `routing.middleware`, `routing.domain`, `authorization.default`, `pages.default_width`, `pages.record_key`, `breadcrumbs.enabled`, `breadcrumbs.home`, `breadcrumbs.navigation`, `navigation.auto_register`, `navigation.target`, `rendering.layout`, `rendering.vite`, `rendering.navigation`, `rendering.wire_navigate`, `lifecycle.hooks`, `diagnostics.max_components`, `diagnostics.max_livewire_hosts`.
