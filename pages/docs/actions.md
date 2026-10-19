# Actions

Pages place actions; gcego/actions runs them.

## Header

Direct children of a page appear in its header:

- every `PageLink`;
- actions and action groups placed in `header`;
- on record pages without an infolist, actions placed in `record` (an infolist shows those itself);
- never the action the page's form submits (`create`, `edit`).

Each action renders through gcego/actions' `ActionRenderer` (an `ActionHost` with the page identity and the record key), so visibility, confirmation, loading states, transactions and feedback are the actions package's.

## Rows and bulk actions

Actions placed in `row` or `bulk` are the table's: gcego/tables shows them per row and per selection, and runs them through gcego/actions with re-resolved, re-authorized records.

## Following results

`PageHost` listens for `gcego-action-completed` (dispatched by gcego/actions and gcego/forms hosts) and asks `PageActionResolver` where to go:

| Page | Action | Destination |
| --- | --- | --- |
| Create | `create` | `RedirectTo` (`view` default): the record's view page, else its edit page, else the list |
| Edit | `edit` | `RedirectTo` (`self` default): stay, with the action's message |
| Record page | `delete`, `force-delete` of this record | the list (Parent or the name without its last segment) |
| Any | row `view` (intent `view`) | the record's view page |

The payload must name an action of this page; the record key must have the target's shape; the target page must exist, have a route, and be openable by the user (with that record). Otherwise the page stays.
