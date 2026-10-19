# Authorization

All decisions are gcego/security's; pages only decide what to ask.

## Pages

A page's Requirement configuration nodes must all allow:

```php
new Permission('subjects.view')
Permission::inTenant('institution.members.view')
new Ability('viewAny', Subject::class)
new Authenticated
new TenantMember('admin')
new PublicAccess
```

A page without requirements follows `authorization.default`: `deny` (default, gcego/security's deny-by-default), `authenticated` or `public`. `PageInspector` reports pages without requirements.

Guests who are denied get an `AuthenticationException` (Laravel sends them to the login page); keep `auth` in `routing.middleware` so routes refuse guests before the page is even resolved.

## Children

A child with its own requirements (a Section, a Table, a widget) is checked with the chain from the page down to it and left out when denied. Layouts and widgets also enforce their own requirements, and every host re-checks its own path.

## Records

See [record pages](record-pages.md): scoped lookup, then the record ability through the model's policy.

## Every request

The page route, `PageHost::mount()` and every `PageHost` update open the page again. A permission revoked between two clicks, a tenant switch (the `gcego.tenant` middleware runs on Livewire updates too) or a record moved out of scope takes effect on the next request. Decisions are memoized only within one request and one principal.

## Links and redirects

`PageLink`s render only when the user may open the target page (and record). After an action, pages redirect only to pages the user may open, choosing among targets the definition allows; the browser event that triggers it is never trusted for the URL.
