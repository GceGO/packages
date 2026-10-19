# gcego/forms

Declarative forms for GCEGO Admin Studio. You declare fields and layouts as
components. The package does the rest:

- accessible Blade rendering, styled with Tailwind through gcego/chroma;
- Livewire hosting, wizard steps and repeaters;
- Laravel validation;
- conditional and reactive fields;
- relationship options read through gcego/data;
- field-level authorization through gcego/security;
- Create and Edit input for gcego/actions.

```php
use Gcego\Forms\Form;
use Gcego\Forms\Fields\{TextInput, Textarea, Select, Repeater};
use Gcego\Forms\Layout\{Section, Grid};

new Form(
    new Section('Basic Information',
        new Grid(2,
            new TextInput('name', 'Subject Name', new Property(TextInput::Required, true)),
            new Select('category_id', 'Category', new Relationship('category')),
        ),
        new Textarea('description', 'Description'),
    ),
    new Repeater('objectives', new TextInput('title', 'Objective'), new Textarea('description', 'Description')),
);
```

Put the form in a blueprint next to a `Source` and `Create`/`Edit` actions,
then mount it:

```blade
<livewire:gcego-form definition="subjects" />                  {{-- create --}}
<livewire:gcego-form definition="subjects" :record="$id" />    {{-- edit --}}
```

## Requirements

- PHP 8.3+ and Laravel 12/13;
- Livewire 4;
- `symfony/html-sanitizer` (for rich text);
- gcego/core, runtime, styling, data, security and actions.

## Security model, in short

| Rule | What it means |
| --- | --- |
| The schema is trusted, the browser is not | Field definitions, rules, options and handler classes come from PHP. The browser only sends values. |
| Unknown keys are refused | A submitted key that is not a declared field (`institution_id`, `is_admin`, a forged repeater key) is refused: a 400 on Livewire updates, a 403 `Denied` result on submission. Mass assignment through forms is not possible. |
| Only editable values are written | Only visible, editable, submitted fields are returned. Disabled, read-only, hidden-by-condition and write-denied fields are dropped. Absent keys never overwrite stored values. |
| Hidden fields stay on the server | `Hidden` values are never sent to the browser; they come from the record or the default. |
| Passwords never go back out | Passwords are never hydrated, rendered or echoed. An empty password changes nothing. |
| Relationship values are checked | Options are read through gcego/data as the current user, scoped to the tenant. Submitted values are re-checked against that same scoped query. |
| Rich text is sanitized | Rich text is sanitized on the server against an allow-list. |
| Uploads are checked and private | Content type is sniffed, size is capped, files are stored privately under random names on allowed disks, and stored paths are never accepted from the browser. |

## Documentation

- [Architecture](docs/architecture.md)
- [Fields](docs/fields.md)
- [Layouts](docs/layouts.md)
- [Validation](docs/validation.md)
- [State, hydration and dehydration](docs/state.md)
- [Conditional and reactive fields](docs/reactive.md)
- [Relationship options](docs/relationships.md)
- [Livewire and actions](docs/livewire.md)
- [Security](docs/security.md)
- [API reference](docs/api-reference.md)

## Testing

```bash
composer test      # Pest
composer analyse   # PHPStan (max)
composer format    # Pint
```
