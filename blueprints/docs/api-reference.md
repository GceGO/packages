# API reference

All classes live under `Gcego\Blueprints`.

## `Blueprint` (`final readonly`, implements `Contracts\BlueprintInterface`, registry `DerivesDefinitions`)

| Member | Returns |
| --- | --- |
| `static define(BlueprintDefinition ...$definitions)` | `Blueprint`; throws `InvalidBlueprintException` |
| `with(BlueprintDefinition ...$definitions)`, `without(string ...$keys)` | validated copy |
| `name()`, `title()`, `slug()` | `string` |
| `description()` | `?string` |
| `model()`, `table()`, `form()`, `infolist()` | `?class-string` |
| `pages()`, `actions()`, `relations()`, `widgets()`, `derivers()` | `list<class-string>` |
| `has(string $key)` | `bool` |
| `definition(string $key)` | `?BlueprintDefinition` |
| `find(class-string<T> $class)` | `?T` |
| `get(string $key)` | normalized value, or `null` |
| `keys()`, `definitions()` | declaration order |
| `references()` | `array<string, list<class-string>>` |
| `identity()` | `Support\BlueprintIdentity` (`name`, `slug`, `class`, `type = "blueprint"`) |
| `metadata()` | `Metadata\BlueprintMetadata` (`name`, `title`, `slug`, `description`, `titleDerived`) |
| `toArray()` | `{name, title, slug, definitions: {key: value}}` |
| `derivedDefinitions()` | `iterable<object>` from the requested derivers |

## Definitions (`Definitions\*`)

Usage, examples and guidance: [definitions guide](definitions.md).

| Class | Base | `make()` |
| --- | --- | --- |
| `Name`, `Title`, `Description`, `Icon` | `TextDefinition` | `make(string $value)` |
| `Model`, `Policy`, `Table`, `Form`, `Infolist` | `ReferenceDefinition` | `make(class-string $class)` |
| `Pages`, `Actions`, `BulkActions`, `Relations`, `Widgets`, `Derive` | `ReferenceCollectionDefinition` (`Countable`, `contains()`) | `make(list<class-string> $classes)` |

Base classes for extension packages:

| Class | Value | API |
| --- | --- | --- |
| `OptionsDefinition` | `array<string, string\|int\|bool\|list<string>\|null>` in schema order | `abstract static schema(): array<string, Option>`, `protected static fromOptions(array)`, `option($name)`, `classes()` |
| `Option` | one typed option | `text()`, `identifier()`, `identifiers()`, `icon()`, `integer(min:, max:)`, `boolean()`, `className()`; `required`, `default` |
| `IdentifierListDefinition` | `list<string>` | `make(list<string>)`, `contains()`, `count()` |

Every definition exposes `const Key`, `static definitionKey()`, `key()` and `value()`.

`DefinitionCatalog`: `register(class, required)`, `has(key)`, `find(key)`,
`all()`, `required()`, `BuiltIn`. `DefinitionType`: `key`, `class`, `required`.

## Contracts (`Contracts\*`)

| Interface | Purpose |
| --- | --- |
| `BlueprintDefinition` | `static definitionKey()`, `key()`, `value()` |
| `ReferencesClasses` | `classes(): list<class-string>` |
| `BlueprintInterface` | the read-only blueprint API above |
| `BlueprintDeriver` | `derive(BlueprintInterface): iterable<object>` |
| `BlueprintResolver` | `has()`, `resolve()`, `names()` |

## Registry (`Registry\*`)

| Class | Purpose |
| --- | --- |
| `BlueprintKind` | registry `DefinitionKind` with id `BlueprintKind::Id` (`blueprint`) |
| `BlueprintProvider` | registry `DefinitionProvider` with `blueprint()` |
| `ProvidesBlueprint` | trait implementing `definitions()` |

## Validation (`Validation\*`)

`BlueprintValidator` (`validate()`, `withDefaults()`), `ReferenceRule`,
`ReferenceRules` (`defaults()`, `add()`, `all()`), `Rules\ClassExists`,
`Rules\ImplementsContract`, `BlueprintValidationResult` (`isValid()`,
`errors()`, `first()`, `errorsWithCode()`, `errorsForKey()`, `messages()`,
`throwIfInvalid()`, `format()`, `toArray()`), `BlueprintValidationError`,
`BlueprintErrorCode`.

## Resolution (`Resolution\*`)

`ReferenceResolver`: `all($blueprint, $key, ?$contract)` and
`one($blueprint, $key, ?$contract)` return references that pass every rule
(and the contract), or throw `InvalidReferenceException`.

`ReferenceRules::lock()` and `isLocked()`; `DefinitionCatalog::markInUse()`
(internal, called by `define()`).

## Other

`BlueprintCollection` (an in-memory `BlueprintResolver`),
`BlueprintsServiceProvider`, `Support\Naming`, `Support\ClassReferences`.

Exceptions under `Exceptions\*` all implement `BlueprintException`:
`InvalidDefinitionException`, `InvalidBlueprintException`, `InvalidReferenceException`,
`DefinitionRegistrationException`, `DuplicateBlueprintException`,
`UnknownBlueprintException`.
