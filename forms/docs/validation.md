# Validation

Every rule comes from the trusted definition. Nothing in the request can add,
remove or change a rule.

## Where rules come from

1. **Required or nullable:** `required` when `Required` or a `RequiredWhen`
   applies, otherwise `nullable`.
2. **The field type:** see `typeRules()` in [fields](fields.md).
3. **`Rules` nodes:** any Laravel rule.
   ```php
   new TextInput('code', new Rules('alpha_num', new Uppercase, fn (?Subject $record) => [Rule::unique('subjects')->ignore($record)]))
   ```
   Closures receive `(?Model $record, FormState $state)` and return a rule or
   a list of rules. Pipe strings such as `'required|string'` are rejected; use
   separate arguments.
4. **Option membership:** for `Select`, `Radio` and `CheckboxList`. A value
   must be one of the options the current user would be shown (static, enum,
   closure, provider or relationship), so disabled options are rejected too.
5. **Uploads:** an uploaded file is checked with `file`, `max` and `mimes`.
   A string value is accepted only if it equals the record's current value.

## Cross-field checks

```php
new Form(..., new Validate(function (Validator $validator, FormState $state, ?Model $record): void {
    if ($state->get('ends_on') < $state->get('starts_on')) {
        $validator->errors()->add('ends_on', 'The end must be after the start.');
    }
}))
```

## What gets validated

Only fields that are visible and editable for the current user. Hidden
conditional fields are neither validated nor written.

## Errors

| Topic | Details |
| --- | --- |
| Keys | Errors are keyed by state path: `name`, `objectives.i3f9c0a1b2d.title`. |
| Labels | Attribute names are the translated labels. |
| Where they show | `FormHost` shows them next to each field (`aria-invalid`, `aria-describedby`) and in a summary (`role="alert"`) with links to the fields. |
| Messages | They never include submitted password values. |

## Rendering versus submitting

`TextInput::Pattern` is anchored as `/^(?:…)$/u` and also emitted as the HTML
`pattern` attribute. Browser checks are a convenience only: the form sets
`novalidate` and the server decides.
