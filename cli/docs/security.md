# Security

## Generated code is authorized by default

| Generator | Default authorization |
| --- | --- |
| panel | `new Ability('access-<name>')`: a Gate ability the CLI never defines (deny by default) |
| page, layout | `new Authenticated`, or `new Ability(--ability)` |
| resource, blueprint, dataset, model metric | the model's policy (`viewAny`, `view`, `create`, `update`) |
| widget | `new Ability('viewAny', Model::class)`; a custom widget requires `--ability` |
| action | `Action::Ability` (a policy method, default camelCase name) plus confirmation |
| workflow | each transition needs a policy ability (`update` to submit, `approve` to decide) |
| settings | `new Ability('<name>.manage')` |
| report | `new Ability('<name>.view')`; export needs `<name>.export` |

Never generated:

- `PublicAccess`, Gate definitions, users or roles;
- Delete actions without `--delete`;
- code that bypasses authorization or tenant scoping (gcego/data and gcego/security apply to everything generated).

## Sensitive fields

The model inspector leaves out:

- `$hidden` attributes;
- anything named like a password, passcode, PIN, secret, token, API key, private key, salt, hash, two-factor, OTP, recovery code, remember token, SSN, card number or CVV;
- keys, timestamps and foreign keys you did not make fillable.

Forms declare only `$fillable` attributes, and gcego/forms rejects undeclared fields.

## Input validation

| Input | Rule |
| --- | --- |
| names | letters, digits, `-`, `_`; no reserved PHP words; at most 64 characters |
| identifiers (module, panel, dataset…) | lowercase kebab case |
| classes (`--model`) | a syntactically valid class name, then an existing Eloquent model; it is not executed beyond instantiation |
| abilities, permissions | `[A-Za-z][A-Za-z0-9_.:-]*` |
| navigation links | a route name or an absolute path |
| icons, paths, domains, guards, themes, layouts, tenant modes | validated against each package's patterns or registries |
| test targets | definition identities, or files inside the application |

Text options (titles, bodies) are escaped into single-quoted PHP strings. Nothing from the command line is evaluated.

## Filesystem

- Targets are resolved inside the application. `..`, NUL bytes, absolute paths elsewhere and symbolic links are refused, and checked again just before writing.
- Writes are staged and moved into place together, with rollback on failure.
- Existing different files are never replaced without `--force`.
- Stub names are validated, and overrides that are symbolic links are ignored.

## Environment

- Generators refuse to write in production unless confirmed interactively.
- Inspection and diagnostics are read-only, and usable in production.
- Generation makes no database changes. Only `gcego:doctor --database` connects, and it only reads.

## Output

Values of keys that look secret are printed as `[redacted]`, and paths are printed relative to the application. Diagnostics never print passwords, keys, tokens or DSNs.
