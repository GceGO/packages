# Validation

## Two entry points

```php
$blueprint->validate();      // gcego/components ValidationResult (memoized)
$blueprint->assertValid();   // throws Gcego\Components\Exceptions\InvalidComponentException

$result = (new BlueprintValidator)->validate($blueprint);   // BlueprintValidationResult
(new BlueprintValidator)->assertValid($blueprint);          // throws InvalidBlueprintException
```

Both reject exactly the same blueprints. `BlueprintValidator` adds structure:
every error carries a code, the blueprint name, the path, and the role when
there is one.

## Error codes

Component schema errors keep their `gcego/components` code with a
`component.` prefix (`component.missing_property`, `component.invalid_value`,
`component.unknown_property`, `component.invalid_child`, ...). Blueprint
composition errors use:

| Code | Meaning |
| --- | --- |
| `blueprint.invalid_role` | a part's `blueprintRole()` is invalid |
| `blueprint.duplicate_singleton` | a second part for a single role |
| `blueprint.role_conflict` | parts of one role disagree on cardinality |
| `blueprint.role_not_allowed` | the policy does not allow the role |
| `blueprint.missing_required_role` | the policy requires a role that no part plays |

`BlueprintErrorCode` holds the blueprint codes.

## What is checked

- the name is present and valid; title, slug, icon and description have valid types and formats;
- no unknown metadata keys;
- every direct child is a `BlueprintPart`;
- every nested component's own schema, with full paths (for example `subjects.table.#0`);
- role validity, cardinality and the policy.

A blueprint is **not** required to have a table, form, source or action.

## Results

```php
$result->isValid();
$result->errors();                              // list<BlueprintValidationError>
$result->first();
$result->errorsWithCode(BlueprintErrorCode::DuplicateSingleton);
$result->errorsWithCode('component.invalid_child');
$result->errorsForRole('table');
$result->toArray();
echo $result->format();
```

A formatted error:

```text
Blueprint "subjects" already has a "table" part at subjects.a; the role allows only one.

Role: table
Expected: one "table" part
Received: 2 "table" parts

Path: subjects.b
```

Component errors keep the original `ValidationError` in `$error->componentError`.

## Strict mode

`InvalidBlueprintException` starts with `Blueprint "subjects" failed
validation with N error(s).`, lists up to ten formatted errors, and exposes
`result()`.
