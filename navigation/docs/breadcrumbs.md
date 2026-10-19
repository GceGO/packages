# Breadcrumbs

```php
new Navigation('admin', new Property(Navigation::Home, 'dashboard'), …,
    new Group('Academic Management',
        new Item(new Property(Item::Name, 'subjects'), new Property(Item::Label, 'Subjects'), new Property(Item::Route, 'admin.subjects.index'),
            new Property(Item::Active, 'admin.subjects.*'), new Property(Item::Breadcrumbs, SubjectBreadcrumbs::class)),
    ),
)
```

```blade
<x-gcego-navigation::breadcrumbs navigation="admin" />
```

On `/admin/subjects/1` this renders:

> Dashboard → Academic Management → Subjects → Chemistry

| Part | Source |
| --- | --- |
| Home | The `Home` item (when it is not the active one). |
| Trail | The active item's groups (labels) and the active item (a link). |
| Provider crumbs | Those of the active item's `BreadcrumbProvider`, for pages below it. |
| Current | The last crumb: `aria-current="page"`, not a link. |

```php
final class SubjectBreadcrumbs implements BreadcrumbProvider
{
    public function breadcrumbs(NavigationContext $context): iterable
    {
        $label = $context->record(Subject::class, $context->parameter('subject'));   // null unless the user may view it

        return $label === null ? [] : [new Breadcrumb($label, '/admin/subjects/'.$context->parameter('subject'))];
    }
}
```

## Rules

| Rule | Details |
| --- | --- |
| Ancestors | Breadcrumbs come from the resolved, authorized navigation, so hidden groups and items never appear. |
| Record labels | `$context->record()` authorizes the record through gcego/security (tenant scopes, policies). A record from another tenant, a missing one or a forbidden one gives null, so its label never leaks. |
| Empty | No active item means no breadcrumbs. `breadcrumbs.enabled` turns them off. |
| URLs | Crumb URLs go through the same safe-URL check as links. Failing providers are reported and left out. |
| Markup | `<nav aria-label="Breadcrumb">` with an `<ol>`. Separators are `aria-hidden` and flipped in right-to-left layouts. |
