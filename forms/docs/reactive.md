# Conditional and reactive fields

```php
new Select('kind', new Options(Kind::class)),
new Radio('track', new Options([...]), new VisibleWhen('kind', 'elective')),
new NumberInput('credits', new RequiredWhen('kind', 'core')),
new TextInput('internal', new DisabledWhen('kind', 'core')),
new Textarea('details', VisibleWhen::filled('title')),          // inside a Repeater: the same item
new TextInput('x', VisibleWhen::using(fn (FormState $s) => $s->get('level') > 3, dependsOn: ['level'])),
```

## Condition forms

| Form | Passes when |
| --- | --- |
| `new VisibleWhen('field', $value)` | the field equals `$value`; a list matches any element; with a multiple field, the value is one of the selected |
| `VisibleWhen::filled('field')`, `::blank('field')` | the field has a value, or has none |
| `VisibleWhen::not('field', $value)` | the field does not equal `$value` |
| `VisibleWhen::using(Closure, dependsOn: [...])` | the closure returns exactly `true` |

The same forms work for `RequiredWhen` and `DisabledWhen`. You can also build
a reusable condition with `Condition::make()`, `filled()`, `blank()`, `not()`
or `using()` and pass it as the first argument.

## Evaluation

Conditions are evaluated on the server, on every render, validation and
dehydration, and always against the current state. Inside a repeater, a field
name refers to a sibling in the same item first.

## Dependencies

| Topic | Details |
| --- | --- |
| Live fields | Fields that other fields depend on (through conditions or `Options(dependsOn: …)`) are rendered `wire:model.live` automatically, so dependents update immediately. |
| Debounce | Live text fields use `wire:model.live.debounce.400ms`. Configure the delay with `gcego-forms.reactive.debounce`. |
| Rejected at definition time | dependencies on fields that do not exist; cycles (`a` depends on `b`, which depends on `a`); a field visible only when it is itself filled. |

## Dependent options

```php
new Select('topic', new Options(fn (FormState $state) => Topic::forKind($state->get('kind')), dependsOn: ['kind']))
```

When `kind` changes, the options are recomputed. A stale `topic` value that is
no longer an option fails validation.
