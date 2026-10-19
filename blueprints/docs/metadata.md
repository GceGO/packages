# Metadata

## Properties

| Constant | Key | Type | Required | Rule | Default |
| --- | --- | --- | --- | --- | --- |
| `Blueprint::Name` | `name` | string | yes | `/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$/`, at most 100 characters | none |
| `Blueprint::Title` | `title` | string | no | non-empty, at most 200 characters | humanized name |
| `Blueprint::Description` | `description` | string | no | | none |
| `Blueprint::Icon` | `icon` | string | no | `#^[a-z0-9][a-z0-9._:/-]*$#`, at most 100 characters | none |
| `Blueprint::Slug` | `slug` | string | no | `/^[a-z0-9]+(?:-[a-z0-9]+)*$/` | slugified name |

Other keys, including the base component's `label` and `id`, are rejected as
unknown properties. Explicit `null` values are invalid; leave a property out
instead.

Names are lowercase only. This makes them case-sensitive without ambiguity:
`Subjects` is simply invalid. Dots are not allowed, so names stay valid
component path segments.

## Derived values

| Name | Title | Slug |
| --- | --- | --- |
| `subjects` | `Subjects` | `subjects` |
| `academic-subjects` | `Academic Subjects` | `academic-subjects` |
| `exam_papers` | `Exam Papers` | `exam-papers` |

The rules (`Support\Naming`) are:

- `humanize()` replaces `-` and `_` with spaces and capitalizes each word;
- `slugify()` lowercases and collapses every run of non-alphanumeric
  characters into one `-`.

For example, `Naming::slugify('Subject Management')` returns `subject-management`.

## `BlueprintMetadata`

```php
$metadata = $blueprint->metadata();

$metadata->name;          // ?string, as declared
$metadata->title;         // explicit or derived; null when the name is invalid
$metadata->slug;          // explicit or derived; null when the name is invalid
$metadata->description;
$metadata->icon;
$metadata->titleDerived;  // bool
$metadata->slugDerived;   // bool
$metadata->toArray();
```

This is a view over the properties, not a second store: change metadata with
`withProperty()`. `$blueprint->title()` and `$blueprint->slug()` are
shortcuts.

## Identity

```php
$identity = $blueprint->identity();   // throws InvalidBlueprintException without a valid name

$identity->name;    // "subjects" (the key: $identity->key())
$identity->slug;
$identity->class;
$identity->type;
$identity->equals($other->identity());
```

Identity is deterministic: no random ids, and no object identity. The same
declaration always yields the same identity.
