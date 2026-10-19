# Saved views

Users save the current search, filters, sorting, page size and column
layout under a name, then load, rename, delete or star one as their
default (loaded on mount when no other state is given).

## Storage

| Topic | Details |
| --- | --- |
| Where | `Contracts\SavedViewRepository`; the default is `DatabaseSavedViewRepository` on `gcego_table_views`. |
| Ids | ULIDs. |
| Scope | Every query is constrained to table, guard, user key and tenant, so another user's, tenant's or table's view id is simply not found. |
| Limit | `gcego-tables.saved_views.max_per_table` (25). Saving under an existing name updates that view. |
| Names | 1 to 60 characters, without control characters. |

## What a view holds

User-editable state only: never scopes, permissions or record keys.

## Restoring

| Rule | Details |
| --- | --- |
| Current permissions apply | The state goes through the lenient normalizer with the user's current column access. Parts that no longer apply (an unreadable column, a removed filter) are skipped, with a notice. |
| Current tenant applies | The query runs through gcego/data with the current tenant and policies. |
| Versions | Views saved by an incompatible state version are refused with a notice. |

Turn it off with `gcego-tables.saved_views.enabled = false`, or per table
with `new Property(Table::SavedViews, false)`.
