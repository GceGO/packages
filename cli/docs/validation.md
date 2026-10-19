# Validation

```bash
php artisan gcego:validate
php artisan gcego:validate --type=modules
php artisan gcego:validate --id=blueprint:subjects --format=json
```

## How it validates

Nothing is re-implemented. `DefinitionValidation` uses:

1. **gcego/registry's non-strict build.** Every discovered file is loaded; files that throw, invalid definitions and duplicate identities become `registration.failed` and `registration.duplicate` entries.
2. **Each kind's validator** (`DefinitionKind::validate()`): the component tree, plus kind rules such as module compilation, workflow consistency, settings keys and analytics references. Problems are `definition.invalid`.
3. **The integration inspectors** of gcego/modules, gcego/panels, gcego/pages and gcego/navigation: missing modules or pages, undeclared panel access, page route conflicts, broken links (`integration.<check>`).
   - These read the application's strict registry, so they run only when every definition loads. Otherwise the report carries a notice saying they were skipped.

Forms, tables, infolists and actions are parts: they are validated within their resource, blueprint or module.

## --type

| Value | Kinds |
| --- | --- |
| `blueprints` | blueprint |
| `modules`, `resources` | module |
| `pages` | page |
| `panels` | panel |
| `layouts` | layout |
| `navigation` | navigation |
| `widgets` | widget |
| `workflows` | workflow |
| `settings` | settings, settings-page |
| `analytics` | dataset, metric, report, analytics-dashboard |

Any registered kind id is also accepted. An unknown type exits with 2, and an unknown `--id` exits with 5.

## JSON schema (version 1)

```json
{
  "schema": 1,
  "valid": false,
  "summary": {"definitions": 7, "valid": 6, "invalid": 1},
  "entries": [
    {"identity": "panel:staff", "kind": "panel", "source": "app/GCEGo/Panels/staff.php", "valid": false,
     "problems": [{"code": "integration.module.missing", "message": "..."}]}
  ],
  "notices": [],
  "exit_code": 3
}
```

Files that failed to register have `"identity": null` and `"kind": null`.
