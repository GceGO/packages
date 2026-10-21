# gcego/actions

Declarative actions for GCEGO Admin Studio: Create, Edit, View, Delete,
Restore, ForceDelete, custom and bulk actions.

- **Execution:** resolved from trusted blueprints, authorized through
  gcego/security, records resolved through gcego/data's scopes, and run in
  Laravel transactions with structured results.
- **Interface:** an accessible Livewire host renders the actions, styled by
  gcego/chroma.

```php
use Gcego\Actions\{Create, Edit, View, Delete, Restore, ForceDelete, Action, BulkDelete};
use Gcego\Actions\Configuration\{Handler, Input};

new Blueprint(
    'subjects',
    new Ability('viewAny', Subject::class),            // gcego/security: who may open the module
    new Source(Subject::class),                         // gcego/data: where records come from
    new Create(new Input(['name' => ['required', 'string', 'max:120']])),
    new Edit(new Input(['name' => ['required', 'string', 'max:120']])),
    new View,
    new Delete,                                         // confirmation required by default
    new Restore,
    new ForceDelete,
    new BulkDelete,
    new Action('publish', 'Publish', new Property(Action::Ability, 'publish'), new Property(Action::Record, true), new Handler(PublishSubject::class)),
);
```

```php
new Delete(
    new Property(Delete::Label, 'Remove Subject'),
    new Property(Delete::Confirmation, true),
);

new Edit(
    new Property(Edit::Label, 'Modify'),
    new Tailwind(new Slot(Tailwind::Wrapper, ['inline-flex', 'items-center', 'gap-2', 'rounded-lg', 'px-4', 'py-2'])),
);

new Action(
    new Property(Action::Name, 'publish'),
    new Property(Action::Label, 'Publish'),
    new Property(Action::Ability, 'publish'),
    new Property(Action::Record, true),
    new Handler(fn (ActionContext $context) => $context->model()->update(['is_published' => true])),
);
```

Show them on a page (each request re-authorizes):

```blade
<livewire:gcego-actions definition="subjects" placement="header" />
<livewire:gcego-actions definition="subjects" placement="row" :record="$subject->id" />
```

Or execute them from code:

```php
$result = app(ActionManager::class)->execute(new ActionRequest('blueprint:subjects', 'delete', record: $id));
$result->status;                  // ConfirmationRequired: $result->confirmation['token']
app(ActionManager::class)->execute(new ActionRequest('blueprint:subjects', 'delete', record: $id, confirmation: $token));
```

## Requirements

- PHP 8.3+, Laravel 13, Livewire 4.4+.
- All of these GCEGO packages at ^0.1: `gcego/components`, `core`,
  `blueprints`, `registry`, `runtime`, `styling`, `data` and `security`.

## Installation

```bash
composer require gcego/actions
php artisan vendor:publish --tag=gcego-actions-config   # optional
php artisan vendor:publish --tag=gcego-actions-views    # optional
```

The provider is auto-discovered and registers:

- the `gcego-actions` Livewire component;
- runtime renderers for `Action` and `ActionGroup`;
- views and translations (`gcego-actions::`);
- styling slots;
- the `gcego/actions` core package.

Actions are only executable when their blueprint is **registered**
(gcego/registry): that is the trust boundary.

## Secure by default

| Concern | Behavior |
| --- | --- |
| Authorization | blueprint/group/action requirements + the action's ability (policy) on the record; custom actions without an Ability or requirement are denied |
| Records | always resolved through the blueprint's scoped `Source`; other tenants, missing and soft-deleted records are 404 |
| Input | only validated values from an `InputResolver`; undeclared or non-writable fields are refused (403); Create/Edit never save without validated input; `fill()` keeps `$fillable`/`$guarded` |
| Confirmation | Delete, ForceDelete and bulk actions need a signed, single-use token bound to action, record or selection, user, guard and tenant; it never replaces authorization |
| Transactions | `Connection::transaction()`, rollback on failure, after-commit side effects |
| Bulk | selection capped (500), resolved in chunks through scopes, every record authorized, all-or-nothing by default |
| Livewire | identity, record, scope and dialog are `#[Locked]`; every request re-resolves and re-authorizes |

## Documentation

- [Architecture](docs/architecture.md)
- [Action definitions](docs/action-definitions.md)
- [CRUD actions](docs/crud-actions.md)
- [Custom actions](docs/custom-actions.md)
- [Authorization](docs/authorization.md)
- [Confirmation](docs/confirmation.md)
- [Transactions](docs/transactions.md)
- [Bulk actions](docs/bulk-actions.md)
- [Livewire](docs/livewire.md)
- [API reference](docs/api-reference.md)

## Testing

```bash
composer test      # Pest: definitions, CRUD, security, transactions, bulk, Livewire, rendering (SQLite)
composer analyse   # PHPStan level max
composer format    # Pint
```

## License

MIT. See [LICENSE.md](LICENSE.md).
