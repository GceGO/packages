# Custom actions

```php
new Action(
    new Property(Action::Name, 'publish'),
    new Property(Action::Label, 'Publish'),
    new Property(Action::Ability, 'publish'),     // policy method SubjectPolicy::publish(User, Subject)
    new Property(Action::Record, true),
    new Handler(PublishSubject::class),
)
```

```php
final readonly class PublishSubject implements ActionHandler
{
    public function __construct(private Repository $config) {}   // injected

    public function handle(ActionContext $context): Subject
    {
        $subject = $context->model();
        $subject->update(['is_published' => true]);
        $context->effects->afterCommit(fn () => event(new SubjectPublished($subject)));

        return $subject;
    }
}
```

**An action needs its own authorization.** Declare either an `Ability`, or
requirement nodes such as `new Permission('subjects.publish')`. A custom
action with neither is denied to everyone, and the inspector reports it.

## `ActionContext`

| Member | Contains |
| --- | --- |
| `action` | the `ResolvedAction` (blueprint, action, path); `identity()` |
| `authorization`, `user()`, `tenant()` | the verified security context |
| `record`, `model()` | the scoped, authorized record (`model()` throws when there is no Eloquent record) |
| `input` | `ValidatedInput` (`get()`, `all()`) |
| `selection` | bulk: the authorized keys |
| `effects` | `redirect()`, `message()`, `withoutRefresh()`, `keepDialogOpen()`, `afterCommit()` |

## Return values

| A handler returns | Result |
| --- | --- |
| an `ActionResult` | that result |
| a model | success with that record |
| `false` | failure (`handler_returned_false`) |
| anything else | success |

Exceptions roll the transaction back and produce a generic failure. They are
reported to Laravel's exception handler, never shown.

## Lifecycle hooks

```php
new Lifecycle(
    prepare: fn (ResolvedAction $action) => ...,               // before authorization; no record
    beforeExecute: fn (ActionContext $context) => ...,         // after checks, inside the transaction
    afterSuccess: fn (ActionContext $context, ActionResult $result) => ...,   // after commit
    afterFailure: fn (?ActionContext $context, ActionResult $result, ?Throwable $e) => ...,
    afterComplete: fn (?ActionContext $context, ActionResult $result) => ...,
)
```

Nothing before authorization receives the record. Post-commit hook exceptions
are reported, not rethrown.

## Validation for custom actions

Add an `Input` node; `$context->input` then holds validated values only.
