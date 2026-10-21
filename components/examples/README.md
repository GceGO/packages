# PHP examples

Start with [full-usage.php](full-usage.php): a clean layout declaration returning a
validated 49-component schema. It expects a booted Laravel application with the
package service provider registered. [dashboard.php](dashboard.php) is the smaller
section/group/item example.

Run either demonstration from the package directory after installing Composer
dependencies:

```bash
php examples/run.php
php examples/run.php --normalized
php examples/run.php --custom
php examples/run.php --custom --normalized
```

[run.php](run.php) supplies standalone Laravel initialization, validates the loaded
schema, inspects it and queries its tree. The sample normalization output contains
only JSON-compatible data; this does not imply every supported package value is
JSON-compatible.

[custom-component.php](custom-component.php) demonstrates a custom builder,
property schema, value object, configuration node and current-scope alias. Its
supporting classes live in `Components`, `Building`, `Values` and `Configurations`.
When copying these into an application, change the example namespaces to `App\...`
and use Composer autoloading instead of the local `require_once` statements.
Persistent catalog registration belongs in an application service provider.

Read the [complete usage guide](../docs/usage.md), [catalog](../docs/component-catalog.md)
and [extension guide](../docs/extension-guide.md). These examples describe UI
structure; they do not install a renderer, submit forms or execute button actions.
