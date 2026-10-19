# Inspection

All inspection commands are read-only and work in production.

## gcego:list

`gcego:list [--type=panels|modules|resources|pages|blueprints|widgets|layouts|navigation|workflows|settings|analytics]` lists identities per type, with resources as `module.resource`. The output is bounded by `--limit` (default `gcego-cli.inspection.limit`) and `--offset`.

## gcego:registry

Reports:

- each discovered definition with its kind and source file;
- the counts per kind;
- the discovery roots, with missing ones marked;
- gcego/registry's cache status;
- the files that failed to register, with duplicates marked. These make the command exit with 3.

It uses a **non-strict** build (`RegistryManager::build(strict: false)`), so one broken file never hides the others. The application's own registry stays strict.

## gcego:inspect <identity>

`php artisan gcego:inspect module:academics` reports:

| Section | Content |
| --- | --- |
| identity, kind, name, scope, source, class, metadata | from the registry descriptor |
| properties | effective properties; closures and objects shown by type |
| requirements | security requirement classes |
| children | the component tree (depth 4, 100 children per node) |
| valid, problems | the kind's validator |
| integration | `ModuleManager::inspect()`, `PanelManager::inspect()`, `PageManager::inspect()`, `NavigationManager::inspect()` or `LayoutManager::inspect()` |
| sections | custom inspectors registered for the kind |

Values whose keys look secret are printed as `[redacted]`, and paths are printed relative to the application. An unknown identity suggests similar ones and exits with 5.

## gcego:routes

Lists routes named `gcego.*` from the router as it is:

- method, URI (parameter placeholders only, never values), name, panel and, with `--middleware`, the middleware;
- filters: `--panel=admin` and `--module=academics`.

## gcego:packages

Lists every GCEGO package (gcego/studio's `PackageIntegrationManager`):

- installed version, the application's constraint from `composer.json`;
- foundation or optional, and integrated or not;
- gcego/studio's compatibility report: PHP, Laravel, Livewire and every package's `require`.

It exits with 5 when a foundation package is not integrated or something is incompatible.

## gcego:assets

gcego/studio's `StudioAssetManager` report:

- Vite entries, manifest and dev server;
- the Tailwind CSS v4 and gcego/chroma imports;
- a second Alpine instance.

Nothing is built. It exits with 6 on problems.

## gcego:cache-status

| Cache | Status from |
| --- | --- |
| gcego/registry | `RegistryManager::status()` (cached, stale, invalid, missing or disabled) |
| Laravel configuration, routes, events | the framework's flags |
| gcego/settings, gcego/analytics, gcego/widgets | the store in use, and how to clear it (these packages have no status API) |

Nothing is cleared. Clearing stays with the owning commands: `gcego:registry-clear`, `gcego:registry-cache`, `gcego:settings:clear-cache`, `config:clear`, `route:clear`.
