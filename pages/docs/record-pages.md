# Record pages

`ViewPage`, `EditPage` and custom pages with `Record` true show one record, identified by `{record}`.

## Keys

The key is the model's key, the same key gcego/tables, gcego/forms, gcego/infolists and gcego/actions use, so every host on the page resolves the same record:

| `RecordKey` | Route constraint | For |
| --- | --- | --- |
| `id` | `[0-9]{1,20}` | integer keys |
| `uuid` | UUID | models using `HasUuids` |
| `ulid` | ULID | models using `HasUlids` |
| `slug` | lowercase slug | models whose primary key is a slug |
| `key` (default) | letters, digits, `-`, `_` | anything else |

A key of the wrong shape is 404 at the route, before any query.

## Lookup and authorization

1. The record is found through the page's `Source` with gcego/data: global scopes, soft deletes, the Source's constraints, the tenant scope and every security scope apply. Out of scope is 404, never 403, so record existence never leaks across tenants.
2. The Source's requirements and the page's record ability (`view`, edit pages `update`, or `RecordAbility`) are authorized through the model's policy. A denial is 403, or 404 with the conceal strategy.

Both happen when the page opens and again on every Livewire update. Children receive only the key (render metadata `record`) and resolve and authorize the record again themselves.

## Labels

`:record` in titles and descriptions, and the record crumb, use `RecordTitle` (or `name`, `title`, `label`), never hidden attributes. Without a usable attribute the label is `#<key>`.

## Disappearing records

If the record is deleted or leaves the user's scope while the page is open, the next update shows "This record is no longer available." with a link back to the list. A denial is never turned into this state: it stays a 403.
