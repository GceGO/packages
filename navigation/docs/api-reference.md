# API reference

Namespace `Gcego\Navigation`.

## Nodes

All nodes extend `NavigationNode` (gcego/morfaw `Component`).

| Class | Shorthand | Properties |
| --- | --- | --- |
| `Navigation` (a blueprint part, role `navigation`) | Name | Label, `Display` (sidebar, topbar, mobile, breadcrumbs), `Home`; slot constants; `navigationName()`, `display()`, `home()`, static `styleSlots()` |
| `Components\Item` | Label, Link | `Route`, `Parameters`, `Url`, `Active`, `MatchQuery`, `Icon`, `Badge`, `BadgeVariant`, `BadgeLabel`, `BadgeTtl`, `Breadcrumbs`, `Tooltip`, Description; `routeName()`, `url()`, `parameters()`, `activePatterns()`, `badgeProvider()`, `breadcrumbProvider()` |
| `Components\ExternalLink` | Label, Url | `NewTab`, `Icon` |
| `Components\Group` | Label | `Icon`, `Collapsed`, `Collapsible` |
| `Components\Dropdown` (a group) | Label | `Align` (start, end) |
| `Components\Heading` | Label | |
| `Components\Divider` | | |

Every node also takes Name, `Order`, `Permission`, `Hidden`, gcego/security
requirements and `Tailwind`. Methods: `nodeName()`, `nodeType()`,
`label()`, `order()`, `icon()`, `requirements()`, `isHidden()`.

## Contracts

| Contract | Method |
| --- | --- |
| `Contracts\NavigationBadgeProvider` | `badge(NavigationContext): NavigationBadge\|int\|null` |
| `Contracts\BreadcrumbProvider` | `breadcrumbs(NavigationContext): iterable<Breadcrumb>` |
| `Contracts\NavigationContributor` | `navigation(): string`, `nodes(): iterable<NavigationNode>` |
| `Contracts\MultipleNavigationsContributor` | extends it: `contributesTo(string $identity): bool`, `nodesFor(string $identity): iterable<NavigationNode>` (one contributor, several navigations) |

## Values

| Class | Members |
| --- | --- |
| `Badges\NavigationBadge` | `(value, variant, label, icon, max)`, `count()`, `text`, `classes()`, `toArray()` |
| `Breadcrumbs\Breadcrumb` | `(label, url, current)` |
| `Breadcrumbs\BreadcrumbCollection` | iterable, countable; `current()`, `toArray()` |
| `Resolution\NavigationContext` | `navigation`, `user`, `tenant`, `route`, `parameters`, `path`, `query`, `host`, `fingerprint`; `parameter()`, `count()`, `record()` |
| `Resolution\ResolvedNavigation` | `tree`, `context`, `nodes`, `active`, `trail`; `find()`, `isEmpty()` |
| `Resolution\ResolvedNode` | `id`, `type`, `component`, `label`, `url`, `active`, `activeDescendant`, `expanded`, `collapsible`, `badge`, `children`, `depth` |
| `Tree\NavigationTree`, `Tree\Node` | the structure; `all()`, `find()` |

## Services

| Class | Members |
| --- | --- |
| `NavigationManager` (singleton) | `identity()`, `resolve()` (404), `contribute()`, `tree()`, `resolved()`, `render(string $identity, ?string $display = null)`, `renderNavigation()`, `breadcrumbs()`, `forgetBadges()`, `inspect()`, `config()` |
| `Resolution\NavigationResolver` (scoped) | `resolve(NavigationTree)`, `context()` |
| `Resolution\VisibilityResolver`, `RouteResolver`, `ActiveStateResolver` | `routeMatches()`, `pathMatches()`, `pathOf()` |
| `Badges\BadgeResolver`, `Breadcrumbs\BreadcrumbResolver` | |
| `Tree\NavigationTreeBuilder` | |
| `Rendering\NavigationRenderer` | the runtime renderer |
| `Rendering\NavigationView` | `$nav` in views |
| `Registry\NavigationKind` | |
| `NavigationConfig` | |
| `NavigationServiceProvider` | `Icons` |

## Blade

```blade
<x-gcego-navigation::navigation name="admin" display="topbar" />
<x-gcego-navigation::breadcrumbs navigation="admin" />
```

## Configuration (`config/gcego-navigation.php`)

| Key | Default |
| --- | --- |
| `visibility.default` | `authenticated` (or `public`, `deny`) |
| `sidebar.compact_toggle` | true |
| `sidebar.default_compact` | false |
| `groups.collapsible` | true |
| `groups.max_depth` | 3 |
| `active.match_query_string` | false |
| `breadcrumbs.enabled` | true |
| `links.absolute` | false |
| `links.wire_navigate` | false |
| `badges.cache_store` | null |
| `contributors` | [] |
| `diagnostics.max_nodes` | 200 |
| `diagnostics.max_badges` | 10 |

Invalid values fail when first read.

## Diagnostics

`NavigationManager::inspect('admin')` returns a
`Diagnostics\NavigationReport`:

| Member | |
| --- | --- |
| `identity` | |
| `nodes` | id, path, type, depth, requirement classes |
| `diagnostics` | `NavigationDiagnostic`: check, level, message, path |
| `isHealthy()`, `errors()`, `warnings()`, `checks()`, `toArray()` | |

Checks: `schema`, `registry`, `structure`, `routes`, `icons`,
`styling`, `authorization`, `accessibility`, `performance`.

Inspection runs no authorization, providers or queries, and reports no
badge values.

## Exceptions (`Exceptions\NavigationException`)

| Exception | When |
| --- | --- |
| `InvalidNavigationException` | definitions, trees, contributions, configuration |
| `DuplicateNavigationItemException` | duplicate node names |
