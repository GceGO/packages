# Bulk actions

```php
new BulkDelete                                    // ability "delete" on every record, confirmation
new BulkRestore                                   // trashed records, ability "restore"
new BulkAction('archive', 'Archive selected',
    new Property(BulkAction::Ability, 'update'),
    new Property(BulkAction::Policy, BulkPolicy::Partial->value),
    new Handler(fn (ActionContext $context) => $context->model()->update(['archived' => true])),
)
```

## How selections are processed

1. **Validation:** the selected keys are untrusted. They must be scalar, are
   de-duplicated, and are limited to `bulk.max_selection` (500), otherwise
   `invalid_selection`.
2. **Pass 1:** records are resolved in chunks of `bulk.chunk_size` (100)
   through the blueprint's scoped `Source`, with gcego/data listing
   authorization, tenant and role scopes, and no COUNT queries. Each record's
   ability is authorized.
3. **Policy:**
   - `AllOrNothing` (default): any missing, foreign or unauthorized key stops
     the action, and **nothing is changed**.
   - `Partial`: only allowed records are processed; the rest are counted as
     `skipped`.
4. **Confirmation:** bound to the exact allowed selection.
5. **Pass 2:**
   - `AllOrNothing`: records are re-resolved and re-authorized inside one
     transaction, and the handler runs once per record. Any failure rolls back
     everything.
   - `Partial`: each record runs in its own transaction, and handler failures
     are counted as `failed`.
6. **Result:** `data` holds `{selected, processed, skipped, failed}`. Skipped
   keys are not itemized, so the result can't reveal whether another
   institution's identifier exists.

Memory stays bounded by the chunk size.

## Queued bulk work

Queued bulk operations are not part of this package. A future workflow
package should:

- issue a gcego/security `SecuredOperation` per run;
- call `ActionManager::execute()` with the selection inside
  `SecurityManager::runOperation()`.
