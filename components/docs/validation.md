# Validation

## Two kinds of checks

**Structural checks** run in constructors and copy methods, and throw
immediately:

| Problem | Exception |
| --- | --- |
| unsupported constructor item, named argument, misplaced or excess shorthand | `InvalidConfigurationException` |
| invalid property key or unsupported value | `InvalidPropertyException` |
| the same key declared twice | `DuplicatePropertyException` |

**Schema checks** run over a whole tree through `validate()` or `assertValid()`,
so every error can be reported with its path.

## Collected mode

```php
$result = $component->validate();   // or app(ComponentValidator::class)->validate($component)

$result->isValid();
$result->errors();                       // list<ValidationError>
$result->first();
$result->errorsAt('subjects.table');
$result->errorsWithCode(ErrorCode::UnknownProperty);
$result->toArray();
echo $result->format();
```

## Strict mode

```php
$component->assertValid();   // returns $component when valid
app(ComponentValidator::class)->assertValid($component);
```

When validation fails, both throw `InvalidComponentException`. The message
lists up to ten formatted errors, and `$exception->result()` returns the full
`ValidationResult`.

## Error format

```text
Invalid property "perPage" on component "fixtures.example".

Expected: integer greater than zero
Received: int 0

Path: subjects.table
```

A `ValidationError` exposes `code`, `message`, `path`, `componentType`,
`componentClass`, `subject`, `expected`, `received` and `hint`. String values in
`received` are truncated to 40 characters, and arrays and objects are described
by type only. Ordinary strings remain visible in bounded diagnostics; do not
assume error messages redact secrets.

## Error codes

| `ErrorCode` | Raised when |
| --- | --- |
| `MissingProperty` | a required property is absent |
| `InvalidType` | a value does not match the property types, including an enum backing value passed instead of the case |
| `InvalidValue` | a value is not in `allowedValues`, or fails the custom `rule`, including an invalid explicit id |
| `UnknownProperty` | the schema does not define the key; includes a "Did you mean" hint when a defined key is within two edits |
| `ChildrenNotAllowed` | a leaf component has children |
| `InvalidChild` | a child is not an instance of an allowed class; reported at the child's path |
| `TooFewChildren` / `TooManyChildren` | the child count is outside `minChildren` / `maxChildren` |
| `InvalidConfiguration` | a configuration node is not an allowed class |
| `DuplicateId` | an explicit id is used more than once in the tree |
| `ConstraintFailed` | a definition constraint returned a message |

## Schema rules

```php
protected static function defineSchema(): ComponentDefinition
{
    return parent::defineSchema()
        ->withType('tables.table')
        ->withProperties(new PropertyDefinition(self::Model, Type::classString()))
        ->withAllowedChildren([Column::class], min: 1, max: 50)
        ->withAllowedConfigurations([Style::class])
        ->withConstraint(function (ComponentInterface $table): ?string {
            return $table->childCount() > 10 && $table->get(self::Paginated) !== true
                ? 'Tables with more than 10 columns must be paginated.'
                : null;
        });
}
```

- **Unknown properties** are rejected for every subclass. Only the generic base
  `Component` is open-ended. Opt in with `->withUnknownProperties()`.
- **Allowed children** match by `instanceof`, so interfaces and parent classes work.
- **Constraints** receive the component and return `null`, a message string, or
  an iterable of messages.

## Testing custom components

```php
it('rejects a misspelled option', function (): void {
    $result = (new Text('name', new Property('serchable', true)))->validate();

    expect($result->first()?->code)->toBe(ErrorCode::UnknownProperty)
        ->and($result->first()?->hint)->toBe('Did you mean "searchable"?');
});
```

## Builder and form-control validation

`$builder->buildValidated()` builds and checks the whole tree.
`$builder->validate()` builds and returns collected schema errors. Neither
`build()` nor `normalize()` validates implicitly. `validate()` may still throw
for cycles/depth violations or errors in trusted application callbacks.

Catalog controls validate configuration types, supported properties and configured
bounds. Text length bounds must be ordered; numeric metadata must be finite and
steps positive. A form control's `required` flag does not require a configured
`value` property or validate incoming request data. Perform server-side request
validation in the consuming application.
