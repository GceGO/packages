# API reference

Namespace `Gcego\Forms`.

## FormManager (singleton)

| Method | Returns |
| --- | --- |
| `schema(Form)` | `Schema\FormSchema` (cached per definition) |
| `hydrate(Form, ?Model $record = null)` | `array` client state |
| `process(Form, array $submitted, ?Model $record = null, ?class-string $model = null)` | `array` validated attributes; throws `ValidationException` or `InvalidStateException` |
| `validateStep(Form, array $submitted, int $step, ?Model $record = null, ?class-string $model = null)` | `void`; throws `ValidationException` |
| `normalize(FormSchema, array)` | `State\FormState` |
| `options(ChoiceField, FormState, ?class-string $model = null)` | `Options\OptionCollection` |
| `access(FormSchema, mixed $record = null)` | `State\FieldAccess` |
| `conditions()`, `config()` | `ConditionEvaluator`, `FormsConfig` |
| `inspect(Form)` | `Diagnostics\FormReport` |

## Components

| Group | Classes |
| --- | --- |
| Form | `Form` (`SubmitLabel`) |
| Text | `TextInput`, `EmailInput`, `PasswordInput`, `Textarea` |
| Numbers and choices | `NumberInput`, `Select`, `Radio`, `CheckboxList`, `Checkbox`, `Toggle` |
| Dates and times | `DatePicker`, `DateTimePicker`, `TimePicker` |
| Other fields | `Hidden`, `FileUpload`, `RichEditor`, `Repeater` |
| Layouts | `Layout\Section`, `Layout\Grid`, `Layout\Tabs`, `Layout\Tab`, `Layout\Wizard`, `Layout\Step` |

Property constants are listed in [fields](fields.md).

## Configuration nodes (`Configuration\`)

| Node | Signature |
| --- | --- |
| `Rules` | `(string\|object ...$rules)`; closures `(?Model, FormState)` |
| `Validate` | `(Closure(Validator, FormState, ?Model): void)` |
| `VisibleWhen`, `RequiredWhen`, `DisabledWhen` | `(string\|Condition $field, mixed $value = true)`, `::filled()`, `::blank()`, `::not()`, `::using()` |
| `Condition` | `::make()`, `::filled()`, `::blank()`, `::not()`, `::using(Closure, list $dependsOn)`, `passes()`, `dependencies()`, `describe()` |
| `Options` | `(array\|Closure\|class-string $source, list<string> $dependsOn = [])` |
| `Relationship` | `(string $name, string $title = 'name', ?Closure $query = null)` |
| `Access` | `(?Requirement $read = null, ?Requirement $write = null, bool $mask = false)`; `mask` shows unreadable values as a mask instead of hiding the field |
| `UsesForm` | marker for custom actions |

## Contracts

| Interface | Methods |
| --- | --- |
| `Contracts\FormComponent` | marker for components allowed in forms |
| `Contracts\OptionsProvider` | `options(FormState, ?string $model)`, `allows(array, FormState, ?string $model)`, `dependencies()` |
| `Contracts\EditorAdapter` | `view(): string` |

## Integration

| Class | Notes |
| --- | --- |
| `Integration\FormInputResolver` | gcego/actions `InputResolver` + `RendersInput`; `::form(ResolvedAction)` |
| `Persistence\SaveRecord` | `ActionHandler` that saves attributes and syncs BelongsToMany |
| `Livewire\FormHost` | alias `gcego-form` |
| `FormSubmitted` | event: `definition`, `form`, `data`, `userKey` |
| `Diagnostics\FormInspector` | `inspect(Form, ?class-string $model)` → `FormReport` (`isHealthy()`, `errors()`, `toArray()`) |

Diagnostic checks:
- `schema`;
- `options`;
- `relationship`;
- `uploads`;
- `renderer`;
- `styling`;
- `reactive`.

## Configuration (`config/gcego-forms.php`)

| Key | Default |
| --- | --- |
| `state.path` | `data` |
| `state.trim_strings` | `true` |
| `uploads.max_size_kb` | `10240` |
| `uploads.disks` | `['local']` |
| `uploads.directory` | `gcego-uploads` |
| `options.limit` | `100` (max 100) |
| `dates.timezone` | `null` (display zone; `null` means `app.timezone`) |
| `reactive.debounce` | `400ms` |

## Exceptions (`Exceptions\FormException`)

| Exception | When |
| --- | --- |
| `InvalidFieldException` | definition errors |
| `InvalidStateException` | an undeclared state path (HTTP 400; a 403 through actions) |

## Service provider

`FormsServiceProvider`:
- publish tags `gcego-forms-config` and `gcego-forms-views`;
- views and translations under `gcego-forms::`;
- core package descriptor `gcego/forms`.
