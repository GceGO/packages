# Validation

## Structural (always, no autoloading)

Each definition validates its own value in `make()` and throws
`InvalidDefinitionException`. The exception exposes `$key`, `$reason` and
`$hint`.

| Definition | Rule |
| --- | --- |
| `Name` | lowercase identifier: `^[a-z][a-z0-9]*([-_][a-z0-9]+)*$`, at most 100 characters; never rewritten, but a suggestion is given |
| `Title` | non-empty after trimming, at most 200 characters |
| `Description` | non-empty after trimming, at most 1000 characters, no control characters except `\n`, `\r`, `\t` |
| `Model`, `Table`, `Form`, `Infolist` | one syntactically valid class name; a leading `\` is removed |
| `Pages`, `Actions`, `Relations`, `Widgets`, `Derive` | a non-empty list (not a map) of valid class names, without case-insensitive duplicates |

`Blueprint::define()` then validates the whole set. It collects every
problem and throws a single `InvalidBlueprintException` whose `result()` is a
`BlueprintValidationResult`:

| Code | Cause |
| --- | --- |
| `blueprint.unsupported_definition` | a key that is not in the `DefinitionCatalog`, or a class claiming a key it does not own |
| `blueprint.duplicate_definition` | a key declared twice (never silently overwritten) |
| `blueprint.missing_required_definition` | `Name`, or a required extension, is absent |

## Integration (registry phase)

`BlueprintValidator::validate($blueprint)` runs the installed
`ReferenceRule`s over `$blueprint->references()`. For each reference the
rules run in order, and only the first failure is reported.

| Code | Rule |
| --- | --- |
| `blueprint.unresolvable_reference` | `ClassExists`: the class, interface or enum cannot be autoloaded (including a parent class from an uninstalled package) |
| `blueprint.incompatible_reference` | `ImplementsContract`: the class does not implement the contract required for its key |

`BlueprintKind::validate()` returns the formatted errors to the registry,
which reports them as an `InvalidDefinitionException` naming the identity
and source, or records them as a failure in non-strict mode.

`gcego-blueprints.validate_references = false` skips this phase only in the
`local` and `testing` environments. Everywhere else the setting is ignored.

## Execution guard

Engines take the references they execute from `ReferenceResolver`, which
runs the full rules regardless of configuration (and, optionally, a contract
check) and throws `InvalidReferenceException` before any class is run. This
also covers blueprints that never went through the registry, and references
that disappeared after a cache was built.

## Fixed configuration

Reference rules lock on the first validation, and required definition types
can't be registered once a blueprint exists. Either attempt throws, so a
process never validates different blueprints by different rules.

## Error shape

```php
$error->code;      // "blueprint.duplicate_definition"
$error->message;   // 'The "table" definition is declared twice (arguments 5 and 8); ...'
$error->blueprint; // "subjects", or "(unnamed)"
$error->key;       // "table"
$error->expected;  // optional
$error->received;  // optional
$error->hint;      // optional
$error->format();  // message plus details, one per line
```
