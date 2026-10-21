# Composition

## Constructor forms

There are exactly three:

```php
new Blueprint('subjects', ...$items);                          // name shorthand
new Blueprint(new Property(Blueprint::Name, 'subjects'), ...);  // advanced syntax
new Blueprint(...$items);                                       // no name: invalid until you add one
```

The rules come from `gcego/morfaw`:

- **Only one positional value is accepted: the name.** A second string throws
  `InvalidConfigurationException` ("Blueprint accepts 1 positional shorthand
  value(s): name").
- **Positional values must come first.** A string after a part throws.
- **Items:**
  - `Property` → metadata;
  - `BlueprintPart` components → parts;
  - `ConfigurationNode`s → configuration (for example styling).
- **Duplicates:** the same property twice, including the shorthand name plus an
  explicit `Name` property, throws `DuplicatePropertyException`.

A string is never interpreted as a title, slug, route or model.
`new Blueprint(Subject::class)` fails validation because a class name is not a
valid blueprint name. Bind models with a source part.

## Roles

```php
BlueprintRole::single('table');      // at most one part
BlueprintRole::multiple('action');   // any number
new BlueprintRole('reports.chart', multiple: true);
```

Role names match `/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/` (at most 64
characters). `BlueprintRole` provides constants for the conventional roles so
packages agree on spelling:

| Constant | Value | Conventional cardinality |
| --- | --- | --- |
| `Source` | `source` | single |
| `Table` | `table` | single |
| `Form` | `form` | single |
| `Infolist` | `infolist` | single |
| `Action` | `action` | multiple |
| `Widget` | `widget` | multiple |
| `Layout` | `layout` | multiple |
| `Navigation` | `navigation` | single |

Cardinality is declared by the part, not looked up from this table. Any other
role name is equally valid.

## Which components are parts

```php
new Blueprint(
    'dashboard',
    new Section('overview',          // PartContainer: its BlueprintPart children are parts too
        new EnrolmentWidget('enrolments'),
    ),
    new Table('table',
        new Column('name'),          // a table's private content, not a part
        new DeleteRowAction('delete'), // not a blueprint action either
    ),
);
```

Parts are the direct `BlueprintPart` children, plus the `BlueprintPart`
children of `PartContainer` parts, at any depth. Classification uses
interfaces only: no namespace heuristics and no reflection.

## Cardinality and conflicts

| Situation | Error code |
| --- | --- |
| Two parts of a single role | `blueprint.duplicate_singleton` (reported at the second and later parts) |
| Parts of one role disagree on `multiple` | `blueprint.role_conflict` |
| A part's `blueprintRole()` throws `InvalidBlueprintPartException` | `blueprint.invalid_role` |

## Policies

The base `Blueprint` requires no role and allows every role. Subclasses tighten this:

```php
final class ResourceBlueprint extends Blueprint
{
    protected static function definePolicy(): CompositionPolicy
    {
        return new CompositionPolicy(
            requiredRoles: [BlueprintRole::Table],
            allowedRoles: [BlueprintRole::Source, BlueprintRole::Table, BlueprintRole::Form, BlueprintRole::Action],
            singleRoles: [BlueprintRole::Source],
        );
    }
}
```

`compositionPolicy()` builds the policy once per class. Policy errors are
`blueprint.missing_required_role` and `blueprint.role_not_allowed`.

## Lookup

| Method | Returns | Behaviour |
| --- | --- | --- |
| `part($role)` | `?BlueprintPart` | `null` when absent; throws `BlueprintCompositionException` for a multiple role, and `DuplicateBlueprintPartException` when several parts claim a single role |
| `requirePart($role)` | `BlueprintPart` | like `part()`, but throws `MissingBlueprintPartException` when absent |
| `parts()` / `parts($role)` | `BlueprintPartCollection` | all parts, or one role's parts, in document order |
| `hasPart($role)` | `bool` | |
| `roles()` | `list<string>` | present roles, in order of first appearance |
| `composition()` | `BlueprintComposition` | the cached classification, including `issues()` |

`BlueprintPartCollection` is immutable. It iterates the parts, and also offers
`all()`, `first()`, `get($index)`, `paths()`, `entries()` (parts with role and
path), `ofRole()` and `count()`.

For a role that no part plays, the cardinality is unknown, so `part()` simply
returns `null`.

## Customization

```php
$blueprint->withPart($part);         // single role: replace in place (and collapse duplicates); multiple: append
$blueprint->withoutRole('action');   // drop direct parts of the role(s)
$blueprint->withProperty(new Property(Blueprint::Title, 'Academic Subjects'));
```

All three return a new instance of the same class, and the original never
changes. Copies are classified and validated independently. `withPart()` and
`withoutRole()` act on direct children only.
