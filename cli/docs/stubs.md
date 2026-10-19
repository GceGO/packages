# Stubs

Generators render stubs: PHP templates with `{{ placeholder }}` slots.

## Overriding

```bash
php artisan vendor:publish --tag=gcego-cli-stubs   # copies them to stubs/gcego/
```

Edit any file there; the others keep using the built-in versions. Precedence:

1. `gcego-cli.stubs.custom_path` (default `stubs/gcego/<name>.stub`); symbolic links are ignored;
2. stub directories contributed by packages (`CliManager::stubs($package, $directory)`). Two packages contributing the same stub name is an error;
3. the built-in stubs.

`StubManager::all()` reports where each stub comes from.

## Rendering rules

- Placeholders are `{{ lowercase_with_underscores }}`. An unknown placeholder, or a malformed one such as `{{ Name }}`, is an error, and nothing is written.
- A customized stub may leave placeholders out.
- Values are typed:
  - **string:** escaped for a single-quoted PHP string;
  - **identifier:** a validated class or namespace name;
  - **code:** fragments the generator composes from validated values.
- No template code is executed: rendering is string replacement. The result must parse as PHP (checked with `PhpToken::tokenize(..., TOKEN_PARSE)`), or the generation fails.

## Stubs and their placeholders

| Stub | Placeholders |
| --- | --- |
| blueprint | imports, name, title, parts |
| module | imports, name, title, properties |
| resource | imports, name, module, model_class, properties, table, form, infolist, actions |
| page | imports, page_class, name, title, requirement |
| panel | imports, name, title, path, properties, requirement, references, access_note |
| form, table, infolist | imports, form/table/infolist, relative |
| widget, widget-data | imports, name, widget / namespace, class, name |
| action, action-definition | namespace, class, name, ability / imports, name, title, ability, handler, relative |
| layout | imports, name, title, columns, requirement |
| navigation | imports, name, title, items |
| notification | namespace, class, name, title, body, type, category, channels |
| workflow | imports, name, title, subject |
| settings, settings-page | imports, name, title, key, requirement, scopes / imports, name, title, group, scope |
| metric, dataset, report | imports, name, arguments / imports, name, model_class, requirement, fields, date_column / imports, name, title, requirement, columns, dataset, export |
| test.pest, test.phpunit | imports, description, body / namespace, imports, class, method, body |

Stubs are versioned with the package: after upgrading gcego/cli, compare your overrides with the new built-in stubs (`vendor/gcego/cli/stubs`).
