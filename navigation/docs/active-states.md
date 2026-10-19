# Active states

One item at most is active (`aria-current="page"`): the one matching the
current page best. The groups holding it are marked as holding it and
expanded.

| Item | Active when |
| --- | --- |
| A route, no parameters | The current route has that name. |
| A route with parameters | The current route has that name and the generated URL is the current path. |
| A path or URL | The current path is that path (same host; trailing slashes ignored). |
| `Active` patterns | Any pattern matches (below). |

## Patterns

Patterns are segment-aware, never substrings.

```php
new Property(Item::Active, 'admin.subjects.*')            // admin.subjects, .index, .show, .edit, .topics.edit …
new Property(Item::Active, ['admin.*.index', '/admin/subjects/*'])
new Property(Item::Active, '/admin/subjects/{subject}')   // exactly one segment there
```

| Pattern | Matches |
| --- | --- |
| Route names | `.`-separated. A trailing `*` matches the name and anything below it. An inner `*` matches one segment. `admin.subject.*` never matches `admin.subjects.index`. |
| Paths | Start with `/`. A trailing `/*` matches the path and anything below it. `*` or `{name}` elsewhere matches one segment. `/admin/subjects/*` never matches `/admin/subjectsx`. |

## Precedence

1. Exact matches (own route or path) beat patterns.
2. Among patterns, the longer pattern wins.
3. On a tie, the first item in display order wins.

So a Dashboard at `/admin` with `Active: '/admin/*'` never steals the
active state from Students on `/admin/students`.

## Query strings

They are ignored by default. With `Item::MatchQuery` (or
`active.match_query_string`), an item's query parameters must all be in
the current URL:

```php
new Item(new Property(Item::Label, 'Active students'), new Property(Item::Url, '/admin/students?status=active'), new Property(Item::MatchQuery, true))
```

## Livewire

During a Livewire update the request is Livewire's endpoint. The
navigation resolves for the page instead: Livewire's original URL, with
its route matched. So active states and breadcrumbs stay correct when a
component re-renders.
