# Items

```php
new Item('Students', '/admin/students')                 // path
new Item('Students', 'admin.students.index')            // route name
new Item(new Property(Item::Name, 'students'), new Property(Item::Label, 'Students'), new Property(Item::Route, 'admin.students.index'), new Property(Item::Icon, 'users'), new Property(Item::Order, 20))
```

| Property | Meaning |
| --- | --- |
| Name | The identity (default: the label as a slug; must be unique in the navigation). |
| Label | Required, at most 120 characters. |
| `Route` | A named route (preferred for internal pages). |
| `Parameters` | Route parameters: scalars, or `":tenant"` for the current tenant's key. |
| `Url` | A root-relative path, or an http(s) or mailto URL. |
| `Link` | The shorthand second value. A path or URL when it starts with `/` or a scheme, otherwise a route name. |
| `Active` | Patterns that also make it active (see [active states](active-states.md)). |
| `MatchQuery` | Require its query string. |
| `Icon` | A gcego icon name. |
| `Order` | Sort key (default 0). |
| `Badge`, `BadgeVariant`, `BadgeLabel`, `BadgeTtl` | See below. |
| `Breadcrumbs` | A `BreadcrumbProvider` for pages below it. |
| `Tooltip`, `Description` | Extra text. |
| `Permission`, `Hidden`, requirements | See [authorization](authorization.md). |

## Routes

| Rule | Details |
| --- | --- |
| Generation | URLs come from Laravel's URL generator: encoded, root-relative by default (`links.absolute` for absolute URLs). |
| Hidden items | An item whose route is missing or cannot be generated (missing parameters), or needs `:tenant` outside a tenant, is not shown. The inspector reports missing routes. |
| Ownership | Navigation never registers routes. |

## Ordering

Siblings are sorted by `Order` (default 0, negative values first), then
declaration order. Equal orders keep declaration order.

## External links

```php
new ExternalLink('Documentation', 'https://docs.example.com')
new ExternalLink('Support', 'mailto:support@example.com', new Property(ExternalLink::NewTab, false))
```

| Rule | Details |
| --- | --- |
| URLs | http(s) and mailto only. `javascript:`, `data:`, `vbscript:`, protocol-relative URLs and quotes are refused at validation. |
| New tab | Default: `target="_blank" rel="noopener noreferrer"` and a screen-reader note "(opens in a new tab)". |
| Icon | An external-link icon is shown. |

## Badges

```php
new Property(Item::Badge, 12)                                   // a count ("12", "99+" above 99; 0 shows nothing)
new Property(Item::Badge, 'New'), new Property(Item::BadgeVariant, 'success')
new Property(Item::Badge, PendingRequestsBadge::class), new Property(Item::BadgeLabel, 'pending requests')
```

```php
final class PendingRequestsBadge implements NavigationBadgeProvider
{
    public function badge(NavigationContext $context): NavigationBadge|int|null
    {
        return $context->count(AccessRequest::class);   // one aggregate query, as the user, in the tenant
    }
}
```

| Rule | Details |
| --- | --- |
| When providers run | When a visible item renders, never at definition, boot or inspection. Hidden items never run them. |
| Data | `$context->count()` goes through gcego/data, so policies and tenant scopes apply. |
| Failures | A failing provider is reported and its badge left out. |
| Caching | `BadgeTtl` caches the badge per user and tenant. `NavigationManager::forgetBadges('admin')` invalidates. |
| Accessibility | `BadgeLabel` is the meaning ("3 pending requests"), read instead of the bare number. Variants are default, primary, success, warning, danger, info. |
