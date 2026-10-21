# Changelog

## Unreleased

- **Added:** Filament-style authoring on gcego/morfaw's live foundation. `Form::make()`, `TextInput::make()`, `Section::make()` and every other field and layout have `make()`. `visible()`, `hidden()`, `required()` and `disabled()` accept closures injected with `$get`, `$record`, `$operation` and typed values, and layouts accept `visible()`/`hidden()` closures. Closures become ordinary server-side conditions, so validation, dehydration and "hidden fields are never saved" apply unchanged.
- **Added:** automatic live fields. The fields closures read are discovered while conditions are evaluated (`FormManager::livePaths()`, `Reactive\FormEvaluation`) and rendered `wire:model.live` without `dependsOn`, including inside repeaters (`objectives.*.title`).
- **Added:** `afterStateUpdated(fn (Set $set, $state, $old) => ...)` in the Livewire host. It may only set declared fields, and what it sets is validated on submit. `default()` accepts closures.
- **Added:** form extensions. `Components::extend('{blueprint}.form', fn (SchemaPatch $patch) => ...)` patches a blueprint's form everywhere it is rendered, hydrated and submitted (`FormManager::extended()`).
- **Fixed:** `Checkbox` with a closure default no longer counts as checked.

- **Added:** `Section::Icon` puts an icon badge in a section header. Forms are restyled: filled inputs with soft borders and a primary focus ring, rounded section cards and a floating save bar (`FormPresets::ActionBar`).
- **Changed:** every native control is replaced by a themed, accessible one built on gcego/chroma's shared controls: `<x-gcego-forms::listbox>` for selects (single or multiple, optional search), `<x-gcego-forms::date-picker>` for date, date-time and time fields (calendar with month and year views, hour and minute columns, Today / Now, Clear), themed checkboxes, radios and choice cards, input groups for prefixes and suffixes, number steppers, a password reveal button, a drag-and-drop upload zone, repeater cards, a stepper wizard and an iconed error summary. Each custom control drives a hidden native element that keeps the field's binding, so deferred, live and debounced `wire:model` and plain `name=""` submits behave as before. Styling uses gcego/swatches semantic tokens (`FormPresets`).
- **Fixed:** the error summary now pluralizes ("There are 4 problems…") instead of printing the raw translation choice string.
- **Changed:** requires gcego/luminaries (its views use `<x-gcego-icon>`).

- `Contracts\PersistsWithRecord` (`stage()`, `persist()`): fields that are not columns (gcego/media uploads) are staged after validation and persisted once the record is saved. Through gcego/actions this runs inside the action's transaction. `FormManager::submission()` returns the data and the after-save callbacks.
- `Field::currentValue(Model $record)`: the record's current value of a field, used for hydration and for which uploads may be kept. Fields that are not columns override it.

## 0.1.0 - 2026-10-17

First release.

- **Form and fields:**
  - `Form` blueprint part;
  - fields `TextInput`, `EmailInput`, `PasswordInput`, `Textarea`, `NumberInput`, `Select`, `Radio`, `CheckboxList`, `Checkbox`, `Toggle`, `DatePicker`, `DateTimePicker`, `TimePicker`, `Hidden`, `FileUpload`, `RichEditor` and `Repeater`;
  - layouts `Section`, `Grid`, `Tabs`/`Tab` and `Wizard`/`Step`.
- **Configuration nodes:** `Rules`, `Validate`, `VisibleWhen`, `RequiredWhen`, `DisabledWhen`, `Options`, `Relationship`, `Access` and `UsesForm`.
- **Schema compiler:** rejects duplicate paths, unknown dependencies, self-hiding fields and dependency cycles.
- **Processing:** secure hydration, normalization, Laravel validation (including nested repeater paths and wizard steps) and dehydration. The `FormManager` service.
- **Relationships:** BelongsTo and BelongsToMany options through gcego/data, scoped and authorized.
- **Field authorization:** field-level read/write/mask through gcego/security `FieldRule`s.
- **Rendering:** accessible Blade renderers registered with gcego/runtime; Tailwind through gcego/chroma slots (`root`, `label`, `input`, `helper`, `hint`, `error`).
- **Livewire:** the `gcego-form` host (`FormHost`) for Create, Edit, custom actions and standalone forms (`FormSubmitted` event).
- **gcego/actions integration:**
  - `FormInputResolver`, which also renders full forms in `ActionHost` dialogs;
  - `Persistence\SaveRecord` for many-to-many sync.
- **Diagnostics:** `FormInspector`.
