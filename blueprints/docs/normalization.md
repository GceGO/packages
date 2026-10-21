# Normalization

```php
$normalized = (new BlueprintNormalizer)->normalize($blueprint);
$withSchemas = (new BlueprintNormalizer(includeSchema: true))->normalize($blueprint);
```

## Shape

```php
[
    'blueprint' => [
        'name' => 'subjects',
        'title' => 'Subject Management',
        'slug' => 'subjects',
        'description' => null,
        'icon' => null,
        'titleDerived' => false,
        'slugDerived' => true,
        'class' => Blueprint::class,
        'type' => 'blueprint',
    ],
    'policy' => ['requiredRoles' => [], 'allowedRoles' => null, 'singleRoles' => []],
    'parts' => [                                       // document order
        ['role' => 'table', 'multiple' => false, 'path' => 'subjects.table', 'class' => ..., 'type' => ...],
        ['role' => 'action', 'multiple' => true, 'path' => 'subjects.create', 'class' => ..., 'type' => ...],
    ],
    'roles' => [                                       // sorted by role name
        'action' => ['multiple' => true, 'paths' => ['subjects.create']],
        'table' => ['multiple' => false, 'paths' => ['subjects.table']],
    ],
    'component' => [ /* ComponentNormalizer output for the whole tree */ ],
]
```

## Guarantees

- **Deterministic.** Equal declarations normalize identically, whether written
  with shorthand or `Property` syntax and in any property order.
- **No duplicate work.** Parts and roles point into the component tree by path;
  the tree is normalized once, by `gcego/morfaw`.
- **Definition-level only.** No callbacks are run, no model is resolved, and
  nothing touches a database or renders HTML. Unsupported values (closures,
  service objects) are rejected when the `Property` is created. `RuntimeValue`s
  become `RuntimePlaceholder`s, exactly as in `gcego/morfaw`.
- **Effective metadata.** Derived titles and slugs are included and flagged.
- **Styling configuration nodes** appear under `component.configurations`,
  never under `parts`.
