# Architecture

## Responsibilities

`gcego/blueprints` owns three things:

1. **Definition objects**: typed, `readonly` value objects (`Name`, `Table`,
   `Pages`, ...) that validate and normalize their own value in `make()`.
2. **Compilation**: `Blueprint::define()` checks the set of definitions
   (supported keys, one definition per key, required keys) and returns an
   immutable `Blueprint`.
3. **Registry integration**: the `blueprint` kind (`BlueprintKind`), its
   discovery roots, the `BlueprintProvider` convention and the
   `BlueprintValidator` reference checks.
4. **Checked resolution**: `ReferenceResolver`, which engines use to take
   references they are about to execute.

It does not own any engine. Tables, forms, infolists, pages, actions,
relations and widgets are referenced by class name, and the package that
owns each capability reads those references.

## Dependency graph

```text
gcego/blueprints ──▶ gcego/registry ──▶ gcego/core ──▶ illuminate/*
        └──────────────────────────────▶ gcego/core
```

Feature packages depend on blueprints, never the other way round. Registry
does not depend on blueprints.

## Phases

| Phase | When | What runs | Loads referenced classes? |
| --- | --- | --- | --- |
| Definition | `X::make(...)` | value validation and normalization | no |
| Compilation | `Blueprint::define()`, `with()`, `without()` | key and cardinality checks against the `DefinitionCatalog` | no |
| Registration | registry discovery, provider loading, `Registry::register()` | `BlueprintKind`: name, `BlueprintValidator`, metadata; opt-in derivation | autoload only (`ClassExists`), never instantiated |
| Resolution | `Registry::get()` from a cached index | entrypoint reloaded, validated again (`validate_on_resolve`) | autoload only |
| Execution | feature engines, through `ReferenceResolver` | full reference rules again (memoized per blueprint), then the engine builds objects | yes, by the engines, only after the check |

## Storage

`Blueprint` keeps one private `array<string, BlueprintDefinition>` in
declaration order. Every value is a string, a list of strings, or an
extension's immutable value, so the object can be shared safely. Accessors
are thin reads over that array:

- `references()` collects `ReferencesClasses::classes()`;
- `pages()` and the other collection accessors return lists;
- `table()` and the other single-reference accessors return the first
  (only) reference, or `null`.

## Extension points

| Extension | Mechanism |
| --- | --- |
| New definition types | `DefinitionCatalog::register(Navigation::class)`: one class per key, process-wide, idempotent |
| Contract checks for references | `ReferenceRules::add(new ImplementsContract($key, $contract))` or a custom `ReferenceRule` |
| Derived registry definitions | `BlueprintDeriver`, requested per blueprint with `Derive::make([...])` |
| Discovery roots | `gcego-blueprints.discovery` (`paths`, `entrypoint`, `enabled`) |

The catalog is static because definitions are plain PHP values, created
before (and without) a container. Rules and the kind are container
singletons because they run inside the registry.

## Configuration lifecycle

Configuration is mutable only while packages register, and fixed once it
is in use:

| What | Open | Fixed |
| --- | --- | --- |
| Optional definition types | always | never (they don't change existing blueprints) |
| Required definition types | until the first `Blueprint::define()` | `DefinitionRegistrationException` afterwards |
| Reference rules | until the first validation | `LogicException` afterwards |
| Reference validation switch | `local` and `testing` only | always on in every other environment |

## Registry ownership

`blueprint` is owned by gcego/blueprints and `module` by gcego/modules. Each
package registers its kind after `DefinitionKinds` resolves, and adds its
roots just before `RegistryConfig` resolves, which makes provider order
irrelevant. Both use `app/GCEGo/Modules` with disjoint entrypoint conventions
(`Subjects/Subjects.php` and `Module.php`). When an entrypoint matches roots
of several kinds, such as the registry's default `module` root, gcego/registry
gives it to the registered kind whose contract accepts the loaded object.

Entrypoints declare classes, so gcego/registry includes them at most once per
process and reuses the result (see `Gcego\Registry\Loading\ClassEntrypoints`).
