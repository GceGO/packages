# Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| `The module "x" does not exist` | Create it (`gcego:make-module X`), or check its name with `gcego:list --type=modules`. |
| `discovery.roots` fails | Two kinds share a folder with the same entrypoint rule, so the second finds nothing: give one its own folder or rule. |
| Exit 4 `GCEGO GENERATION FAILED` | A target file exists with other contents. Inspect it, choose another name, or pass `--force`. |
| `Refusing to write files in production` | Generate in development and deploy, or confirm interactively. |
| `... is not covered by a PSR-4 rule` | The target folder of a class (support, notification, test) must be autoloadable: adjust `gcego-cli.paths` or `composer.json`. |
| A resource page answers 403 | The model has no policy, or the policy denies (`viewAny`, `view`, `create`, `update`). |
| A generated panel answers 403 for everyone | Define its Gate ability (`access-<name>`). |
| An action is never available | Add the policy method named by `Action::Ability` (e.g. `publishSubject`). |
| `has no fillable attributes to edit: the resource is read-only` | Declare `$fillable` on the model, then regenerate with `--force` (or add fields yourself). |
| Fields were "inferred" | The table could not be read (no connection, not migrated): check the generated fields. |
| `gcego:validate` notice "Integration checks ... were skipped" | Fix the files that failed to register first. |
| `cli.commands` fails | Another package registered a `gcego:*` name the CLI owns: rename that command. |
| `A ... generator needs gcego/...` (exit 5) | `composer require` the package named. |
| A custom stub renders invalid PHP | The message gives the line; fix `stubs/gcego/<stub>.stub` or delete it to use the built-in one. |
