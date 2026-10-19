# Commands

All commands accept Laravel's `--no-interaction`, `--quiet` and `--verbose`. Reports also accept `--format=text|json`; listings accept `--limit` and `--offset`, capped by `gcego-cli.inspection.limit`.

| Command | Options | Exit codes |
| --- | --- | --- |
| `gcego:about` | `--format` | 0 |
| `gcego:doctor` | `--database`, `--production`, `--format` | 0, 6 |
| `gcego:list` | `--type`, `--limit`, `--offset`, `--format` | 0, 2 |
| `gcego:registry` | `--kind`, `--limit`, `--offset`, `--format` | 0, 3 (failures or duplicates) |
| `gcego:inspect {identity}` | `--format` | 0, 2, 3 (invalid), 5 (not found) |
| `gcego:validate` | `--type`, `--id`, `--format` | 0, 2, 3, 5 |
| `gcego:routes` | `--panel`, `--module`, `--middleware`, `--limit`, `--offset`, `--format` | 0 |
| `gcego:assets` | `--format` | 0, 6 |
| `gcego:packages` | `--format` | 0, 5 |
| `gcego:cache-status` | `--format` | 0 |
| `gcego:make {generator?} {name?}` | `--set=option=value`, `--list`, `--force`, `--dry-run`, `--format` | 0, 2, 4, 5 |
| `gcego:make-*` | see [generators.md](generators.md) | 0, 1, 2, 4, 5 |

`php artisan help gcego:make-resource` shows each command's options.

## Naming

`gcego:<verb>` for framework-wide commands; `gcego:make-<kind>` for generators. Package-specific commands keep their package prefix (`gcego:settings:inspect`, `gcego:workflows:process-deadlines`). The CLI never registers a name another package owns. `gcego:doctor` fails `cli.commands` if another command shadows one of the CLI's names.

## Interactive and non-interactive use

Interactive generators ask for missing required options:

- the module, chosen from the existing ones;
- the model (default `App\Models\<Name>`);
- the test type and target.

With `--no-interaction`, nothing is asked: a missing required option is an error (exit 2) naming what to pass. Security-relevant options such as the ability of a custom widget are never guessed.
