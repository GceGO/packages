# Definitions

A page definition is an immutable gcego/components tree. Build it with properties and children; nothing runs until the page is opened.

```php
new ViewPage(
    new Property(ViewPage::Name, 'subjects.view'),     // identity, route name, default path
    new Property(ViewPage::Title, ':record'),
    new Permission('subjects.view'),                  // gcego/security requirement
    new Source(Subject::class),                       // gcego/data: where records come from
    new PageLink('Edit', 'subjects.edit'),             // header link
    new Infolist(new Text('name', 'Name')),            // the part the page coordinates
)
```

## Names and identities

| Name | Registry identity | Route name | Default path |
| --- | --- | --- | --- |
| `about` | `page:about` | `gcego.about` | `about` |
| `subjects.create` | `page:subjects-create` | `gcego.subjects.create` | `subjects/create` |
| `subjects.edit` (EditPage) | `page:subjects-edit` | `gcego.subjects.edit` | `subjects/{record}/edit` |
| `subjects.view` (ViewPage) | `page:subjects-view` | `gcego.subjects.view` | `subjects/{record}` |

Names are lowercase segments (letters, digits, `-`, `_`) joined by `.`, at most five segments and 100 characters. Discovery roots with a `scope` prefix identities (`page:school.subjects`); related pages (parents, siblings, `PageLink` targets) resolve within the same scope.

## Children

A page accepts blueprint parts (Source, Table, Form, Infolist, Action, ActionGroup, layout components, Navigation), widgets and page components (`Content`, `PageLink`). Anything else is a validation error.

## Validation

`$page->validate()` (and registration) reports, besides property types:

- a missing or malformed name;
- type requirements: an index page needs a Table, create and edit pages a Form, a view page an Infolist; record pages, index and form pages need a `Source`;
- path problems: unsafe segments, duplicate or optional parameters, `{record}` missing on a record page or present on another, `Where` constraints on unknown parameters, anchored or invalid patterns;
- a page that is its own Parent.

## Create and edit pages

`CreatePage` and `EditPage` are `FormPage`s. Without an action named `create` (or `edit`), the page adds gcego/actions' `Create` (or `Edit`) placed in the form footer only, so the form host submits through it and the header does not show it. Declare your own to change its label, handler or confirmation.
