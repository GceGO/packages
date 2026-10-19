# Lifecycle

`PageLifecycle::open()` (through `PageManager::open()`, the page route or `PageHost`) runs:

| Stage | What happens | Can fail with |
| --- | --- | --- |
| `Resolved` | The definition, from gcego/registry by identity | 404 |
| `ContextCreated` | Route parameters validated (shape, `Where`); principal, tenant, locale, timezone, fingerprint, page state | 404 |
| `Authorized` | Page requirements, or `authorization.default` | 403, login redirect |
| `RecordResolved` | Record pages: `{record}` shape, scoped lookup, record ability | 404, 403 |
| `Prepared` | Title, description, breadcrumbs, header action paths | |
| `Interaction` | Livewire updates only (stages 1-5 ran again) | |
| `Rendering`, `Rendered`, `Completed` | `PageManager::renderContext()` | |

Hooks (`PageLifecycleHook`) are told after each stage completed. They cannot grant access; they may stop a request by throwing.

## Memoization

An open page is memoized for the request by identity, record, parameters, state, request object and security fingerprint. The page route and its `PageHost` therefore open the page once per request (one record query), and the next request opens it again.

## Context

`PageContext` holds the identity, the definition, the route name, the validated parameters, the user, the tenant key, locale, timezone, fingerprint, `PageState` and, once prepared, the `PageHeader`. Record pages get a `RecordPageContext` with the authorized model, its key and its label. Contexts stay on the server.
