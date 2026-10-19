# Action definitions

## Properties (`Action::*`)

| Constant | Type | Default |
| --- | --- | --- |
| `Name` | lowercase name (`publish`, `send-report`) | required for `Action`; built-ins have one |
| `Label`, `Description`, `Icon` | strings | from the name / type |
| `Placement` | placement or list (`ActionPlacement::Header`, `Toolbar`, `Row`, `Record`, `FormFooter`, `Bulk`, `Context`, or registered ones) | by type |
| `Intent` | `primary`, `secondary`, `destructive` | by type |
| `Ability` | Gate ability / policy method | by type; none for `Action` |
| `Record` | `true` needs a record, `false` takes none, `null` optional | by type; `null` for `Action` |
| `Confirmation` | `bool` (overrides configuration) | configuration per type |
| `Hidden`, `Disabled` | `bool`: not rendered / rendered disabled; **neither executes** | `false` |
| `Transaction` | `bool` | `gcego-actions.transactions.enabled` |
| `Idempotent` | `bool`: requires an idempotency key | `false` |
| `SuccessMessage`, `FailureMessage` | translation key or text (`:label` replaced) | by type |
| `Order` | int, lower first within a placement | by type |

The shorthand is `new Action('name', 'Label', ...)`; built-ins use
`new Delete('Label', ...)`. Named arguments are rejected, as for every
component.

## Configuration nodes

| Node | Purpose |
| --- | --- |
| `Handler(Closure\|class-string<ActionHandler>)` | the work |
| `Input(array $rules, array $labels = [], array $messages = [])` | validated input without gcego/forms; rule keys are the only writable fields |
| `Confirmation(?title, ?message, ?confirmLabel, ?cancelLabel)` | dialog text (and turns confirmation on) |
| `Transaction(bool $enabled = true, ?string $connection = null, int $attempts = 1)` | transaction behavior |
| `Lifecycle(prepare, beforeExecute, afterSuccess, afterFailure, afterComplete)` | trusted hooks |
| gcego/security `Permission`, `Ability`, `ResourceAccess`, ... | extra requirements of this action |
| gcego/security `FieldRule` | per-user read and write access of `Input` fields |
| gcego/chroma `Tailwind` | instance classes (slots `root`, `primary`, `secondary`, `destructive`) |

## Groups

```php
new ActionGroup('more', 'More', new Edit, new Delete)
```

A group orders actions and shares a label. Every child keeps its own
authorization, confirmation and visibility.

## Placements

```php
app(ActionManager::class)->placements()->register('kanban.card');
app(ActionManager::class)->actions('blueprint:subjects', ActionPlacement::Row);  // ordered by Order, then declaration
```
