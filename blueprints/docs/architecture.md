# Architecture

## Relationship to `gcego/morfaw`

`Blueprint` **extends** `Gcego\Components\Component`. Everything the component
engine already does is reused unchanged:

| Concern | Provided by gcego/morfaw |
| --- | --- |
| Constructor parsing, shorthand, duplicate keys | `Component::__construct`, `shorthand()` |
| Properties and metadata storage | `Property`, `PropertyCollection` |
| Schema rules for metadata values | `PropertyDefinition` rules in `Blueprint::defineSchema()` |
| "Only parts may be children" | `withAllowedChildren([BlueprintPart::class])` |
| Tree, paths, traversal, cycle safety | `ComponentTree`, `ComponentWalker` |
| Schema validation of the whole tree | `ComponentValidator` |
| Normalization of the tree | `ComponentNormalizer` |
| Immutable copies that keep the subtype | `withProperty()`, `withChildren()`, ... |
| Styling and other configuration | `ConfigurationNode` (never children) |

Blueprint adds only blueprint semantics: the metadata schema and its defaults,
role classification, cardinality rules, part lookup, blueprint-level
validation codes, normalization, inspection, and in-memory collections.

### Composition rules are part of the component schema

`Blueprint::defineSchema()` registers a schema constraint that reports composition
issues. `$blueprint->validate()` and `assertValid()`, which registries and
runtimes already call on any component, therefore reject duplicate singleton
parts and similar mistakes. `BlueprintValidator` reports the same issues in
structured form (code, role, path) and removes the plain-message duplicates.

## Relationship to `gcego/core`

There is none. A blueprint is a definition, so it is constructed, validated
and normalized through the registered component foundation. The component
provider requires a Laravel application; the GCEGO kernel remains independent.
The dependency graph is:

```text
gcego/blueprints ──▶ gcego/morfaw ──▶ illuminate/*
gcego/core       ──▶ (illuminate/*)          # no dependency between blueprints and core
```

Laravel integration (discovering blueprints, registering them as GCEGO
packages or extensions) is the job of `gcego/registry`, which can depend on
both.

## Decisions and deviations from the original plan

- **`Blueprint` extends `Component`.** The component contracts support it
  cleanly, so there is a single tree, a single validator and a single
  normalizer.
- **Roles are value objects declared by parts** (`BlueprintRole`: name and
  cardinality). There is no central role registry, so future packages add
  roles without changing this package.
- **`CompositionPolicy` replaces the planned `BlueprintDefinition`.** Per-class
  rules (required, allowed and single-only roles) sit beside the component
  `ComponentDefinition` rather than duplicating it.
- **`PartContainer`** was added so that layout parts can group other parts.
  Without it, a part's children are its private content: a table's row actions
  are not blueprint actions.
- **`BlueprintResolver`** is the lookup contract. `BlueprintCollection`
  implements it in memory, and `gcego/registry` will provide the discovering
  implementation.
- **Not created:**
  - `BlueprintFactory`: the constructor already covers every supported input,
    and nothing needed adapting.
  - `BlueprintAware`: components are immutable and have no parent pointers, so
    a part cannot know its blueprint.
  - A separate part resolver: lookup lives on `Blueprint` and `BlueprintComposition`.
- **Model-first shorthand (`new Blueprint(Subject::class, ...)`) is not
  supported.** The only positional value is the name. A class string is
  rejected by the name rule, and binding a model is the job of a source part
  owned by `gcego/data`. This keeps every positional string unambiguous.
- **`Label` and `Id` are removed from the blueprint schema.** `Title` is the
  display text and `Name` is the identity, so there are no competing
  properties.

## Composition rules

1. **Parts:**
   - the blueprint's direct children implementing `BlueprintPart`;
   - recursively, the `BlueprintPart` children of `PartContainer` parts.

   Other descendants are not parts.
2. A direct child that is not a `BlueprintPart` fails validation
   (`component.invalid_child`).
3. Parts of one role must agree on cardinality (`blueprint.role_conflict`).
4. A single role allows one part (`blueprint.duplicate_singleton`). The policy
   can force a role to be single.
5. The policy can restrict roles (`blueprint.role_not_allowed`) or require them
   (`blueprint.missing_required_role`). The base `Blueprint` policy does
   neither, so listings, forms, dashboards and custom features are all valid.

Composition is computed once per instance and cached. The cache is keyed on
the immutable children collection and the name, because `Component::__clone()`
is final and cannot reset subclass caches.

## Identity

| Concept | Source |
| --- | --- |
| name | `Blueprint::Name`, required, lowercase |
| slug | `Blueprint::Slug`, else derived from the name |
| title | `Blueprint::Title`, else derived from the name (presentation, not identity) |
| class | the PHP class |
| type | `blueprint`, or the subclass's class name / `withType()` |

Instance identity (which PHP object) is deliberately not part of a
blueprint's identity, so equal declarations have equal identities. Global
uniqueness of names is `gcego/registry`'s job; `BlueprintCollection` only
rejects duplicates within itself.

## Boundaries

- **Registry:** `gcego/registry` discovers and registers blueprints.
  This package provides the stable inputs: `BlueprintInterface`,
  `identity()`, `metadata()`, `assertValid()`, `BlueprintNormalizer` and
  `BlueprintResolver`.
- **Runtime:** `gcego/runtime` renders blueprints. It reads `roles()`,
  `parts()`, `part()` and the component tree; nothing here executes or renders.
- **Styling:** Tailwind declarations are `ConfigurationNode`s, stored in
  `configurations()` and normalized as configuration, never treated as parts.
- **Security:** there are no authorization methods on blueprints.
