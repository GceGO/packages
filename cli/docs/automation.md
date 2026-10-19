# Automation

## Non-interactive use

With `--no-interaction`, or in CI where stdin is not a TTY, no command prompts:

- a missing required option is an error (exit 2) that names the option;
- in production, generators refuse to write (exit 2), because confirming needs a person.

## JSON output

`--format=json` prints exactly one JSON document to stdout:

- `"schema": 1` (bumped on breaking changes to field names or meaning);
- no ANSI colours, progress or decoration;
- secret-looking values redacted.

Generator JSON:

```json
{"schema": 1, "generator": "page", "successful": true, "dry_run": true,
 "created": ["app/GCEGo/Pages/reports.php"], "overwritten": [], "skipped": [], "conflicts": [],
 "warnings": [], "errors": [], "next_steps": ["..."], "exit_code": 0,
 "files": [{"path": "app/GCEGo/Pages/reports.php", "description": "Page \"reports\"", "operation": "create", "namespace": null, "class": null, "bytes": 412}]}
```

Errors (any command):

```json
{"schema": 1, "successful": false, "error": "...", "exit_code": 5, "reason": "missing dependency"}
```

## Exit codes

| Code | Meaning |
| --- | --- |
| 0 | success |
| 1 | internal failure |
| 2 | invalid input |
| 3 | validation failed |
| 4 | file conflict (nothing written) |
| 5 | missing dependency |
| 6 | diagnostic failure |

## Generating from scripts

```bash
php artisan gcego:make-resource Subject --model="App\Models\Subject" --module=academics --no-interaction --format=json
php artisan gcego:make page Reports --set=ability=view-reports --set=type=dashboard --dry-run
```

`gcego:make <generator>` runs any generator with `--set=option=value`; use `--set=flag=true` for flags.
