# Diagnostics

```bash
php artisan gcego:doctor
php artisan gcego:doctor --production
php artisan gcego:doctor --database
php artisan gcego:doctor --format=json
```

Every check is read-only. The doctor never migrates, clears caches, writes files, installs dependencies or creates users; remediations are printed for you to run. A check that throws is reported as its own failure and the others still run. The command exits with 6 when any check fails (warnings don't count).

Statuses: `pass`, `warning`, `failure`, `skipped`, `not_applicable`. Each result has `id`, `label`, `status`, `message`, `remediation` and `package`.

## Checks

| Id | Source | Verifies |
| --- | --- | --- |
| `platform.compatibility` … `storage.writable` | gcego/studio | platform, packages, core boot, configuration, registry, panel routes, authentication, panel access, tenancy, assets, storage |
| `production.*` | gcego/studio (`--production`) | config, route and view caches; debug; HTTPS; session; autoloader; built assets |
| `discovery.roots` | CLI | no two kinds share a folder with the same entrypoint rule |
| `runtime.renderers` | gcego/runtime's inspector | every component type has a renderer |
| `styling.themes` | gcego/chroma's inspector | themes and slots |
| `definitions.valid` | CLI validation | every definition is valid |
| `cache.store` | configuration | not the array driver in production |
| `queue.connection` | configuration | not sync in production when queue-using packages are installed |
| `scheduler.tasks` | the scheduler | `gcego:workflows:process-deadlines` and `gcego:analytics:run-schedules` are scheduled when installed |
| `settings.definitions`, `analytics.definitions`, `workflows.definitions` | each package's inspector (without runtime queries) | definitions are consistent |
| `testing.toolkit` | Composer | gcego/testing and Pest or PHPUnit |
| `cli.commands` | Artisan | no other command shadows a CLI command |
| `database.connection`, `database.migrations` | `--database` only | the connection; pending migrations (read from the migrations table) |

## Custom checks

Implement `Gcego\Cli\Contracts\DiagnosticCheck` (`id()`, `label()`, `usesDatabase()`, `run(): DiagnosticResult`). Register it with `CliManager::check()` or in `gcego-cli.diagnostics.checks`.

- A check with `usesDatabase()` true is skipped without `--database`.
- Built-in ids are reserved.

## Redaction

Output never includes secret values. The doctor's messages name settings, not their values; any value whose key looks like a password, secret, token, key, credential or DSN is printed as `[redacted]`; and paths are printed relative to the application.
