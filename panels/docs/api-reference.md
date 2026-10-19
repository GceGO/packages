# API reference

| Class | Purpose |
| --- | --- |
| `Panel` | the definition; `panelName()`, `title()`, `path()`, `domain()`, `routeName()`, `guard()`, `middleware()`, `accessPolicy()`, `tenant()`, `dashboard()`, `navigationName()`, `modules()`, `pages()`, `unlistedPages()`, `branding()`, `userMenu()`, `requirements()`, `declaresAccess()`, `styleSlots()` |
| `Definitions\ModuleReference` | `new ModuleReference('academics')` |
| `Definitions\PageReference` | `new PageReference('reports')`; `PageReference::Navigation` |
| `Branding\Branding` | `Name`, `Logo`, `LogoDark`, `CompactLogo`, `Favicon`, `Tagline`, `HomeUrl` |
| `Menu\UserMenuItem` | `new UserMenuItem('Profile', 'profile', ...requirements)` |
| `Contracts\PanelExtension` | `routes(Router, PanelDescriptor)`, `topbar(PanelShell): ?Htmlable`, `body(PanelShell): ?Htmlable` |
| `Extensions\PanelExtensions` | `add()`, `classes()`, `all()`, `topbar()`, `body()` |
| `Contracts\PanelAccessPolicy` | `allows(Authenticatable $user, PanelContext $context): bool` |
| `PanelManager` | `names()`, `has()`, `panel()`, `descriptor()`, `current()`, `url()`, `pageUrl()`, `switcher()`, `register()`, `extend()`, `respond()`, `inspect()`, `inspectAll()` |
| `Context\PanelContext` | `panel` (descriptor), `user`, `guard`, `tenant`, `routeNamespace`; `name()`, `identity()`, `tenantKey()`, `owns($routeName)` |
| `Context\PanelContextResolver` | `current()`, `require()` |
| `Access\PanelAccess` | `allows(PanelContext)`, `decide(PanelContext): PanelAccessResult` |
| `Http\Middleware\AuthorizePanel` | `gcego.panel:<name>` |
| `Registry\PanelKind` | the `panel` registry kind |
| `Registry\PanelDefinitions` | `descriptors()`, `names()`, `has()`, `descriptor()`, `resolve()` |
| `Registry\PanelMembership` | `pages()`, `pageIdentity()`, `modulePages()` |
| `Routing\PanelRouteRegistrar` | `register(Router)`, `registerPanel(Router, PanelDescriptor)`, `prefix()`, `middleware()` |
| `Routing\PanelRoutes` | `pageRoute()`, `pageRoutes()`, `homeRoute()`, `url()` |
| `Navigation\PanelNavigationContributor` | `panelNodes(PanelDescriptor)` |
| `Switching\PanelSwitcher` | `links(PanelContext): list<PanelLink>` |
| `Menu\UserMenu` | `links(PanelContext): list<UserMenuLink>` |
| `Shells\PanelShellFactory` | `current(string $title)`, `make(PanelContext, string $title)` |
| `Assets\PanelAssetResolver` | `vite(Panel)` |
| `Diagnostics\PanelInspector` | `inspect(string): PanelReport` (`healthy()`, `codes()`, `toArray()`) |
| `Exceptions\*` | `InvalidPanelException`, `PanelNotFoundException` (404), `PanelAccessDeniedException` (403); all implement `PanelException` |
