# Modules

```php
return new Module(
    new Property(Module::Name, 'academics'),       // required unless the module has a single resource
    new Property(Module::Title, 'Academic Management'),
    new Property(Module::Description, 'Subjects, topics and syllabi.'),
    new Property(Module::Icon, 'star'),
    new Property(Module::Slug, 'academics'),       // URL prefix of every page (default: none)
    new Property(Module::Navigation, true),        // default: gcego-modules.navigation.auto
    new Property(Module::NavigationGroup, 'Academics'),
    new Property(Module::NavigationOrder, 10),
    new Property(Module::Version, '1.2'),          // metadata only
    new Permission('academics.access'),            // required by every page of the module

    new Resource(...),
    new DashboardPage(...),                        // module pages are named "academics.<name>"
);
```

- **Shorthand:** `new Module('academics', 'Academic Management', …)` sets the Name and Title.
- **Identity:** `module:academics` (scoped roots give `module:scope.academics`).
- **Names:** module names are lowercase words separated by `-`.

## Validation

`ModuleKind` validates modules when they are registered (in strict mode, invalid modules throw at build), with:

- the component rules;
- `Module::problems()`;
- compilation.

Typical errors:

- no Name with several resources;
- the same resource twice;
- a relation naming a resource the module doesn't declare;
- an empty module;
- a page sharing a resource's name.

## Immutability

Definitions are immutable component trees. `withProperty()`, `withAppendedChildren()` and the other `with…()` methods return new definitions. Construction never queries, registers routes, renders or mounts Livewire.
