# Fields

Each field takes either shorthand or `Property` syntax:

```php
new TextInput('name', 'Subject Name');
new TextInput(new Property(TextInput::Name, 'name'), new Property(TextInput::Label, 'Subject Name'));
```

Without a label, the label is the headline of the name (`first_name` →
"First Name"). Names must match `/^[a-z_][a-z0-9_]{0,63}$/i`.

## Common properties (`Field::…`)

| Constant | Type | Effect |
| --- | --- | --- |
| `Required` | bool | `required` rule, `required` and `aria-required` attributes |
| `Default` | mixed | initial value for new records (and the value of `Hidden`) |
| `Placeholder`, `HelperText`, `Hint` | string | helper and hint are linked with `aria-describedby` |
| `Disabled`, `Readonly` | bool | shown but never written |
| `Hidden` | bool | not rendered and never written |
| `Dehydrated` | bool (true) | `false` validates but never returns the value |
| `Live` | bool | send updates immediately (fields that others depend on are live automatically) |
| `ColumnSpan` | 1–4 | span inside a `Grid` |
| `Autofocus` | bool | |

## Field types

| Field | Properties | Rules it adds |
| --- | --- | --- |
| `TextInput` | `Type` (text, email, url, tel, search, password), `MinLength`, `MaxLength`, `Pattern` (anchored, unicode), `Autocomplete`, `Prefix`, `Suffix` | `string`, `min`, `max`, `email`, `url`, `regex` |
| `EmailInput` | as `TextInput` with type `email` | `email` |
| `PasswordInput` | `Confirmed` (adds `<name>_confirmation`), `Revealable`, `Strong` (`Password::defaults()`) | never hydrated; an empty password is skipped |
| `Textarea` | `Rows`, `MinLength`, `MaxLength`, `Counter` | `string`, `min`, `max` |
| `NumberInput` | `Integer`, `Min`, `Max`, `Step` | `integer` or `numeric`, `min`, `max` |
| `Select` | `Multiple`, `Searchable`, plus `Options` or `Relationship` | `array` when multiple, plus option membership |
| `Radio`, `CheckboxList` | `Options` or `Relationship` (`CheckboxList` is multiple) | option membership |
| `Checkbox`, `Toggle` | | `boolean`, or `accepted` when required |
| `DatePicker` | `Min`, `Max` (Y-m-d) | `date_format:Y-m-d`, `after_or_equal`, `before_or_equal` |
| `DateTimePicker` | `Timezone` (display zone; stored in `app.timezone`) | `date_format:Y-m-d\TH:i` |
| `TimePicker` | | `date_format:H:i` |
| `Hidden` | `Default` | server-side value only |
| `FileUpload` | `Multiple`, `MaxFiles`, `MaxSize` (KB, capped by config), `Accept` (extensions), `Disk`, `Directory` | `file`, `max`, `mimes` (content-sniffed) |
| `RichEditor` | `Allowed` (elements), `MaxLength`, `Editor` (an `EditorAdapter` class) | `string`, `max`; sanitized on the server |
| `Repeater` | `MinItems`, `MaxItems` (≤ 200), `AddLabel`, `Reorderable`; children are fields or layouts | `array`, `min`, `max`; children validate as `objectives.<key>.title` |

## Rich editors

The default editor is an accessible textarea: the user writes HTML, and the
server keeps only the allowed elements. To plug in a JavaScript editor, write
an `EditorAdapter` whose `view()` returns a Blade view that receives the field
variables. Whatever the editor sends is still sanitized on the server.

## Custom fields

Extend `Field`, then implement:

- `view()`;
- `typeRules()`;
- optionally `normalizeInput()`, `hydrate()` and `dehydrate()`.

The `FieldRenderer` renders every `Field` subclass. Its view receives:

- **identity and binding:** `$field`, `$path`, `$id`, `$binding` (`wire:model…` or `name=`), `$live`;
- **content:** `$label`, `$value`, `$options`, `$items`;
- **state flags:** `$required`, `$disabled`, `$redacted`;
- **errors and description:** `$errors`, `$describedBy`;
- **styling:** `$span`, `$styles`.
