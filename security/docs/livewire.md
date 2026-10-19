# Livewire

Livewire public properties and browser state are untrusted. Authorization
holds because every request re-resolves trusted server state and checks it
again.

## `<livewire:gcego-blueprint>` (gcego/runtime)

The host resolves its blueprint through the runtime on every request, and the
runtime calls `RenderAuthorizer`, which gcego/security binds:

- **Mount and every interaction** check the definition's requirements with the
  current user and tenant.
- **Revoked access:** a membership that ended, a tenant switched to one without
  the permission, or a revoked permission makes the next interaction 403.
- **Locked identity:** the blueprint identity is `#[Locked]`, so it can't be
  pointed at another definition.
- **No authorization objects** are stored in public properties.

## Your components (future gcego/forms, gcego/tables)

```php
final class SubjectEditor extends Component
{
    #[Locked]
    public int $subject = 0;

    public string $name = '';

    public function save(): void
    {
        // Every request: scoped lookup + policy.
        $record = app(SecurityManager::class)->record(new Source(Subject::class), $this->subject, 'update');
        $fields = app(SecurityManager::class)->fields(['name', new FieldRule('is_published', write: new Permission('subjects.publish'))], $record);

        $record->update(app(FieldAuthorizer::class)->writable(['name' => $this->name], $fields));
    }
}
```

The rules:

- **Lock identifiers** (`#[Locked]`). Even so, re-resolve the record through
  `record()` in each action, never from a cached model.
- **Authorize the ability for the action** (`update`, `delete`) inside the
  action, not only in `mount()`.
- **Pass client input through `FieldAuthorizer::writable()`.**
- **Keep helpers private:** private and protected methods are not callable
  actions.

`tests/Fixtures/Livewire/SubjectEditor.php` and
`tests/Integration/DefinitionsAndLivewireTest.php` demonstrate the following:

- forged record ids;
- another tenant's record (404 on mount);
- read-only fields set through public state (403);
- revoked permissions between interactions (403);
- forged method calls.
