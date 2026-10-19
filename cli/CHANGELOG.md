# Changelog

## Unreleased

- `gcego:doctor`'s `discovery.roots` now fails only when two kinds share a folder with the same entrypoint rule (gcego/registry scans shared folders per kind).
- Tests: provider boot in Composer's package-discovery order, every package view compiling, every package migration up and down, delete and cross-tenant mutation end to end.

## 0.1.0 - 2026-10-18

First release.

- **Commands:** 30, registered only in the console:
  - inspection and diagnostics: `gcego:about`, `gcego:doctor`, `gcego:list`, `gcego:registry`, `gcego:inspect`, `gcego:validate`, `gcego:routes`, `gcego:assets`, `gcego:packages`, `gcego:cache-status`;
  - generation: `gcego:make` and nineteen `gcego:make-*` generators.
  - `gcego:install`, `gcego:studio:health` and the registry cache commands remain gcego/studio's and gcego/registry's.
- **Generators:** blueprint, module, resource, page, panel, form, table, infolist, widget (stat, trend, chart, progress, recent records, custom), action, layout, navigation, notification, workflow, settings, metric, dataset, report and test (Pest or PHPUnit, using gcego/testing).
  - Model inspection reads `$fillable`, casts, `$hidden` and the table's columns (types, NOT NULL, defaults), and leaves sensitive attributes out.
  - Generated definitions are authorized by default: a Gate ability, authentication, the model's policy, or policy abilities on actions and workflow transitions.
- **Generation engine:**
  - `Generator` contract, immutable `GenerationPlan` and `GenerationResult`;
  - `GenerationTransaction`: staged, all-or-nothing writes with compensating rollback and temporary-file cleanup;
  - conflict detection, `--force` and `--dry-run`;
  - `PathResolver`, which refuses traversal, paths outside the application and symbolic links;
  - `NamespaceResolver`, from the application's PSR-4 rules;
  - `NameNormalizer`, built on Laravel's `Str`.
- **Stubs:** `StubManager` resolves application overrides first, then package contributions (collisions refused), then built-ins. `StubRenderer` validates placeholders, escapes typed values and checks that the result parses as PHP.
- **Inspection:**
  - `Catalog` (a non-strict registry build per command);
  - `DefinitionReporter`, which adds the module, panel, page, navigation and layout inspectors and custom inspector sections;
  - route, package, asset and cache inspection.
- **Validation:** `DefinitionValidation` uses each kind's validator and the package inspectors, reports registration failures and duplicates, supports `--type` and `--id`, and has a stable JSON schema.
- **Diagnostics:**
  - `Doctor` runs gcego/studio's health checks and the CLI's own checks: discovery roots, renderers, styling, definitions, cache, queue, scheduler, settings, analytics, workflows, testing toolkit, command collisions, and with `--database` the connection and migrations;
  - custom checks run in isolation.
- **Extensibility:** `CliManager` registers generators, diagnostic checks, definition inspectors and stub directories, with collision detection.
- **Output:** `--format=json` with a schema version, secret redaction, and documented exit codes 0–6.
