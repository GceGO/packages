# State

## Stateful components

```php
use Gcego\Runtime\Contracts\StatefulComponent;
use Gcego\Runtime\Exceptions\InvalidStateException;

final class ExampleSearch extends Component implements BlueprintPart, StatefulComponent
{
    public function initialState(): array
    {
        return ['query' => '', 'page' => 1];
    }

    public function acceptState(string $field, mixed $value): mixed
    {
        return match ($field) {
            'query' => is_string($value) && mb_strlen($value) <= 50
                ? trim($value)
                : throw InvalidStateException::rejected($field, 'must be a string of at most 50 characters.'),
            'page' => is_numeric($value) && (int) $value >= 1
                ? (int) $value
                : throw InvalidStateException::rejected($field, 'must be a positive integer.'),
            default => throw InvalidStateException::unknownPath($field),
        };
    }
    // ...
}
```

(This is the `tests/Fixtures/ExampleSearch.php` fixture.)

- **Fields:** each declared field is an identifier.
- **Values:** values are `null`, scalars or lists of scalars, never objects.
- **Validation:** `acceptState()` validates and normalizes every client value.

## State paths

Each stateful **occurrence** gets one bucket, keyed by its occurrence path
with `.` replaced by `:`:

| Occurrence path | Bucket key | Field property |
| --- | --- | --- |
| `subjects.search` | `subjects:search` | `state.subjects:search.query` |
| `subjects.search#1` (the same instance used again) | `subjects:search#1` | `state.subjects:search#1.query` |

Path segments never contain `.` or `:`, so keys cannot collide. They are stable
as long as the definition is unchanged. Siblings and reused instances never
share state.

In templates:

```blade
@php($state = $context->state())
<input wire:model.live="{{ $context->wireModel('query') }}" value="{{ $state['query'] ?? '' }}">
```

## Boundaries

`StateCoordinator`:

- builds the initial map from the trusted, server-side definition;
- `sanitize()` drops unknown buckets and fields on every hydrate, and fills
  missing ones;
- `accept()` lets an update through only for `state.<known bucket>.<declared field>`
  with a value the component accepts. Replacing the whole `state` or a whole
  bucket is rejected.

It never resolves classes from client input, and Livewire's hydration is used
unchanged. State lives only in the Livewire snapshot of one component on one
page, so it cannot leak across requests or users; the shared services hold none.
