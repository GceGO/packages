# Extension guide

For the list of built-in definitions, and where definitions that aren't built
in belong, see the [definitions guide](definitions.md).

## Adding a definition type

The core only builds in definitions that GCEGO engines read: Name, Title,
Description, Icon, Model, Policy, Table, Form, Infolist, Pages, Actions,
BulkActions, Relations, Widgets and Derive. Everything else belongs to the
package that reads it, which registers it in its service provider's
`register()` method:

```php
DefinitionCatalog::register(Navigation::class);
```

### Choosing a shape

| The capability is... | Extend | Example |
| --- | --- | --- |
| data with several fields | `OptionsDefinition` | `Navigation::make(group: 'Academics', icon: 'book-open', sort: 2)` |
| a list of names | `IdentifierListDefinition` | `Permissions::make(['view', 'create', 'subjects.export'])` |
| one class the engine instantiates | `ReferenceDefinition` | `Exporter::make(SubjectsExporter::class)` |
| several classes | `ReferenceCollectionDefinition` | `Reports::make([AttendanceReport::class])` |

Prefer data over classes. Use a class reference only when the engine will
instantiate or call that class.

### Options definitions

```php
final readonly class Navigation extends OptionsDefinition
{
    public const string Key = 'navigation';

    public static function definitionKey(): string
    {
        return self::Key;
    }

    public static function schema(): array
    {
        return [
            'group' => Option::text(required: true),
            'icon' => Option::icon(),
            'sort' => Option::integer(default: 0, min: 0, max: 1000),
            'visible' => Option::boolean(default: true),
            'badge' => Option::className(),
        ];
    }

    public static function make(string $group, ?string $icon = null, int $sort = 0, bool $visible = true, ?string $badge = null): self
    {
        return self::fromOptions(['group' => $group, 'icon' => $icon, 'sort' => $sort, 'visible' => $visible, 'badge' => $badge]);
    }
}
```

- Give `make()` named, typed parameters. PHP then checks types and names
  before `fromOptions()` checks values.
- `null` means absent: the default applies, or the option is reported if
  it's required.
- `className()` options are references. They're stored without autoloading,
  and checked by the reference rules in the registry and by the
  `ReferenceResolver`.
- Engines read `$blueprint->find(Navigation::class)?->option('group')`.

### Identifier lists

```php
final readonly class Permissions extends IdentifierListDefinition
{
    public const string Key = 'permissions';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
```

Identifiers are lowercase, made of letters and digits separated by `.`, `_`
or `-`, distinct, and in a non-empty list.

### Rules

- Keys are lowercase identifiers (`navigation`, `reports.charts`) of at most
  64 characters, and each key belongs to one class.
- Validate in `make()`, and keep the object immutable.
- Register required types (`required: true`) only from `register()`. Once a
  blueprint exists, registering one throws.

Working versions of these examples live in `tests/Fixtures/Extensions`.

## Checking references against a feature contract

```php
// TablesServiceProvider::register()
$this->callAfterResolving(ReferenceRules::class, function (ReferenceRules $rules): void {
    $rules->add(new ImplementsContract(Table::Key, TableDefinition::class));
});
```

Implement `ReferenceRule` for anything more specific: `appliesTo($key)`,
`check($key, $class)` returning an error message or `null`, and `code()`.

## Deriving registry definitions

```php
final readonly class SubjectPages implements BlueprintDeriver
{
    public function derive(BlueprintInterface $blueprint): iterable
    {
        foreach ($blueprint->pages() as $page) {
            yield new PageDefinition(/* ... */);   // a definition of a registered kind
        }
    }
}
```

A blueprint opts in with `Derive::make([SubjectPages::class])`. Derivers take
no constructor arguments, must be deterministic, and must not yield
blueprints.
