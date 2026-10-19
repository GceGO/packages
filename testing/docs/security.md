# Security

Every security assertion asks `gcego/security` for the acting user and tenant, with no stub. A passing "cannot" assertion means the server refuses.

<!-- example:security-guide -->
```php
use Gcego\Data\Source;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Fields\FieldAccess;
use Gcego\Security\Fields\FieldRule;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Models\Subject;

$secret = Permission::inTenant('subjects.secrets');

GcegoTest::security()
    ->actingAs($editor, 1)
    ->assertCan('update', $algebra)
    ->assertCannot('delete', $algebra)
    ->assertAuthorized(new Authenticated)
    ->assertForbidden($secret, DenialReason::MissingPermission)
    ->assertDefinitionAllowed('blueprint:subjects')
    ->assertRecordAccessible(new Source(Subject::class), $algebra->id)
    ->assertRecordHidden(new Source(Subject::class), $southern->id)
    ->assertFieldAccess(['name', new FieldRule('secret_note', read: $secret, mask: true)], 'secret_note', FieldAccess::Redacted)
    ->assertWriteRejected(['name'], ['name' => 'X', 'institution_id' => 2]);
```

## 403 or 404

- **`assertRecordHidden()` (404)**: the record is out of scope (another tenant, a forged key, deleted). It must look missing, so nothing leaks about its existence.
- **`assertRecordForbidden()` (403)**: the record is in scope and the policy refuses the ability.
- **`assertConcealed($requirement)`**: a requirement denied with 404.
- **`assertForbidden($requirement, $reason)`**: a requirement denied with 403, optionally with a `DenialReason`.

## Negative tests

Write the attack, not just the absence of a button:

- **Forged identifiers** (`'1 OR 1=1'`, `'../../etc/passwd'`, `-1`) must be not found everywhere: data, actions and infolists.
- **Submitted fields** the user may not write must be refused (`assertWriteRejected()`, action `assertDenied()`) or dropped (form `assertDropped()`).
- **Confirmation tokens** are bound to the user, tenant, action, record and selection. A token replayed by another user must fail.
- **Hidden actions** must be refused when executed (`assertUnavailable()` *and* `execute()->assertDenied()`).
- **Livewire requests** with forged snapshots, forged tenants or a revoked user must be refused ([livewire.md](livewire.md)).

The toolkit's own `tests/Security/IsolationTest.php` is a complete example.

## Sensitive data in failures

Failure output redacts the values of keys that match `gcego-testing.assertions.redact`:

- the defaults are password, secret, token, api key, authorization, cookie, credential, private key, `remember_token` and `two_factor_*`;
- matching happens at any depth.

Values under other keys are printed, so use synthetic data. `Redactor::scrub($text, $secrets)` masks known secret strings in free text.
