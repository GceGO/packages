# Actions

`GcegoTest::action($definition, $action)` runs `ActionManager::execute()`, the pipeline the Livewire action host uses:

1. Resolution and availability.
2. Record lookup in scope.
3. Authorization.
4. Idempotency.
5. Confirmation token.
6. Input validation.
7. Transaction and handler.
8. Effects and the `ActionCompleted` event.

Nothing is bypassed.

<!-- example:actions -->
```php
use Gcego\Testing\GcegoTest;

GcegoTest::action('subjects', 'publish')
    ->actingAs($admin, 1)
    ->record($draft)
    ->assertAvailable()
    ->execute()
    ->assertSuccessful();

GcegoTest::action('subjects', 'rename')
    ->actingAs($admin, 1)
    ->record($algebra)
    ->input(['name' => str_repeat('x', 60)])
    ->execute()
    ->assertValidationFailed(['name']);

GcegoTest::action('subjects', 'delete')
    ->actingAs($admin, 1)
    ->record($algebra)
    ->confirmed()          // answers the confirmation with the issued token
    ->execute()
    ->assertConfirmationRequired()
    ->assertSuccessful();
```

- **Confirmation**: `confirmed()` answers a confirmation the way a user does. The first request returns a signed, single-use token; a second request sends it back. Without `confirmed()`, a confirmable action stops at `assertConfirmationRequired()`.
- **Result assertions**:
  - `assertStatus($status)`, `assertSuccessful()`, `assertDenied($reason)`, `assertNotFound()`;
  - `assertValidationFailed($fields)`, `assertFailed($reason)`, `assertConfirmationRequired()`;
  - `assertReason()`, `assertMessage()`, `assertRedirect()`;
  - `result()` returns the raw `ActionResult`.
- **Availability**: `assertAvailable()` and `assertUnavailable()` check what the user is *offered*. Offering is not authorization, so always also `execute()` and assert the server refuses.

## Bulk actions

<!-- example:bulk -->
```php
use Gcego\Testing\GcegoTest;

GcegoTest::action('subjects', 'bulk-delete')
    ->actingAs($admin, 1)
    ->select([$algebra, $draft])
    ->confirmed()
    ->execute()
    ->assertSuccessful()
    ->assertBulk(processed: 2, selected: 2);
```

- **Selections with foreign keys**:
  - All-or-nothing actions are denied with `selection_unavailable` when any selected key is out of scope.
  - Partial actions skip those keys.
- **Proving cross-tenant refusal**: `GcegoTest::tenant($key)->assertSelectionRefused()` passes a selection made only of foreign keys and expects nothing to be processed.
