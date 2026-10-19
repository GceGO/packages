# API reference

All classes are in `Gcego\Cli`.

## Extension contracts

```php
interface Contracts\Generator
{
    public function id(): string;                    // "report-card": lowercase, digits, "-", "."
    public function description(): string;
    public function requires(): array;               // GCEGO packages it needs
    public function options(): array;                // option => description
    public function plan(Generation\GenerationContext $context): Generation\GenerationPlan;
}

interface Contracts\DiagnosticCheck
{
    public function id(): string;
    public function label(): string;
    public function usesDatabase(): bool;
    public function run(): Diagnostics\DiagnosticResult;
}

interface Contracts\DefinitionInspector
{
    public function id(): string;
    public function kinds(): array;                  // registry kinds it describes
    public function inspect(object $definition, \Gcego\Registry\Index\DefinitionDescriptor $descriptor): array;
}
```

A generator only plans: it never writes or prompts. Extend `Generators\AbstractGenerator` to get:

- `file()`: renders a stub into a `PlannedFile` with its conflict status;
- `moduleDirectory()`, `assertAvailable()`, `assertPanel()`, `requirement()`, `model()`, `warn()`, `next()`;
- the `--test` handling.

## CliManager

| Method | Purpose |
| --- | --- |
| `generator(class-string\|Generator)` | register a custom generator (`gcego:make <id>`) |
| `generators(): array<string, Generator>` | built-in and custom generators |
| `resolve(string $id): Generator` | one generator |
| `check(class-string\|DiagnosticCheck)` | register a doctor check |
| `inspector(class-string\|DefinitionInspector)` | register an inspector |
| `stubs(string $package, string $directory)` | contribute stubs |

## Generation

| Class | Purpose |
| --- | --- |
| `GenerationContext(name, options, force)` | `string()`, `required()`, `flag()`, `list()` |
| `GenerationPlan(generator, files, requires, warnings, errors, nextSteps, inputs)` | `valid()`, `conflicts()`, `writes()`, `toArray()` |
| `PlannedFile(path, relativePath, contents, description, operation, namespace, class)` | one file |
| `FileOperation` | `Create`, `Unchanged`, `Conflict`, `Overwrite` |
| `GenerationTransaction` | `commit(plan): GenerationResult`, `preview(plan): GenerationResult` |
| `GenerationResult` | `successful`, `created`, `overwritten`, `skipped`, `conflicts`, `warnings`, `errors`, `nextSteps`, `dryRun`, `exitCode` |
| `NameNormalizer` | `className()`, `slug()`, `snake()`, `camel()`, `plural()`, `singular()`, `title()`, `identifier()`, `qualifiedClass()` |
| `PathResolver` | `resolve()` (safe absolute path), `relative()` |
| `NamespaceResolver` | `forDirectory()`, `qualify()` |
| `DefinitionPaths` | `directory(kind)`, `isDiscovered()`, `entrypoint()` |
| `ModelInspector` | `model()`, `inspect()`: fields, excluded, verified, table, key, title, warnings |
| `FieldCode` | `form()`, `table()`, `infolist()`, `imports()` |

## Stubs

| Class | Purpose |
| --- | --- |
| `StubManager` | `path()`, `contents()`, `all()`, `addDirectory()` |
| `StubRenderer` | `render(stub, array<string, StubValue>)`, `placeholders()`, `assertParses()` |
| `StubValue` | `string()`, `identifier()`, `code()` |

## Inspection, validation and diagnostics

| Class | Purpose |
| --- | --- |
| `Inspection\Catalog` | non-strict registry build per command: `descriptors()`, `descriptor()`, `definition()`, `has()`, `names()` |
| `Inspection\DefinitionReporter` | `inspect(identity): array` |
| `Inspection\RouteInspector` | `routes(?panel, ?module)` |
| `Inspection\PackageInspector` | `report()` |
| `Inspection\CacheInspector` | `report()` |
| `Validation\DefinitionValidation` | `run(kinds, ?identity): ValidationReport`, `kindsFor(type)` |
| `Diagnostics\Doctor` | `run(database, production): DiagnosticReport` |
| `Diagnostics\DiagnosticResult` | `pass()`, `warning()`, `failure()`, `skipped()`, `notApplicable()` |
| `Output\Redactor` | `redact(value)` |

## Exceptions

All implement `Exceptions\CliException` (`exitCode()`):

- `InvalidNameException` (2);
- `GenerationException` (`invalid()` 2, `missing()` 5, `because()` 1);
- `UnsafePathException` (2);
- `StubException` (1);
- `ExtensionException` (1).
