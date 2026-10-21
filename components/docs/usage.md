# Complete usage

The package defines validated UI structure. A consuming package renders the
structure and implements behavior. The complete, runnable example is
[`examples/full-usage.php`](../examples/full-usage.php). It returns an immutable
`Layout\Schema` containing 49 components, including navigation, metrics, tabs,
a chart, a table, a profile form, activity history and a confirmation modal.

## Run the examples

From this package directory, with Composer dependencies installed:

```bash
php examples/run.php
php examples/run.php --normalized
php examples/run.php --custom
php examples/run.php --custom --normalized
```

The runner initializes Laravel's facade application and registers the required
`ComponentServiceProvider`. It then loads a schema declaration, validates it,
queries its tree and inspects it. `--normalized` prints the sample's canonical
array as JSON. These examples use scalar/array data and a value object that
normalizes to an array. Arbitrary enum cases and runtime placeholders need an
explicit transport policy; normalization is not a general JSON serializer.

## Use the declaration in Laravel

Install the package and leave Laravel package discovery enabled. If discovery is
disabled, register `Gcego\Components\ComponentServiceProvider` in
`bootstrap/providers.php`, as described in [Laravel integration](laravel.md).

Copy `examples/full-usage.php` to a declaration file such as `app/Ui/admin.php`.
The declaration file contains no bootstrap code and returns a schema. In a
controller or application service:

```php
use Gcego\Components\Facades\Components;

$schema = require app_path('Ui/admin.php');
$schema->assertValid();

$definition = Components::normalize($schema);
$inspection = Components::inspect($schema);
$tree = Components::tree($schema);
$table = $tree->findById('users-table');

// Pass $definition to your rendering/transport layer.
```

The example uses local demonstration records and flags. Supply your application's
authorized data and conditions in the consuming application. It does not query a
database, register routes or execute the declared form/button actions.

## Declaration layers

| Declaration | Responsibility |
| --- | --- |
| `Components::schema([...])` | Build the root layout registry. |
| `Component::section('dashboard')` | Declare a major screen area. |
| `Component::group('metrics')` | Group related nodes. |
| `Component::item('statistics')` | Declare a leaf node. |
| `->components([...])`, `->child([...])` | Append children, skipping conditional null entries. |
| `Component::collect($iterable)` | Build a dynamic list into an immutable component collection. |
| `Component::textInput('email')` and other catalog factories | Start a builder for a specific component type. |

The root is named `admin` in the full example, producing paths such as
`admin.dashboard.workspace.overview.users`. Without an explicit root name, the
schema root's path is `#0`. Explicit ids such as `users-table` provide stable
lookup independent of a component's path; ids must be unique within a tree.

## Builder composition

The example uses `setMany()`, `setIfMissing()`, `when()`, `apply()` and
`prependChildren()`. Builders are immutable: capture the returned builder.
Conditions evaluate while declaring the layout, and only the selected callback
runs. Callbacks must return the transformed builder. Child transformations remain
lazy; `collect()` builds entries immediately, while `children()` on a builder
returns its unbuilt entries. See the complete [builder API](api-reference.md#buildingcomponentbuilder).

`$builder->get()` and `has()` inspect explicit properties; the built component's
`get()` and `has()` also include schema defaults. Explicit null is distinct from
an absent value in both cases. `setIfMissing()` therefore preserves explicit null.

## Validate before exporting

```php
$component = Component::textInput('email')->required()->buildValidated();
$errors = $component->validate();
$normalized = $component->normalize();
```

`build()` and `normalize()` do not enforce schema validity. Use `buildValidated()`
for a builder or `assertValid()` for a built schema before exporting. Collected
validation reports schema errors; structural errors, traversal limits and failures
in application callbacks may still throw. See [validation](validation.md).

A form control's `required()` flag describes UI behavior. It is different from
`Schema\Field::string('name')->mustExist()`, which requires a property on a
component definition. Neither replaces server-side validation of submitted data.

## Custom components and builders

[`examples/custom-component.php`](../examples/custom-component.php) uses classes
in their respective example folders:

| File | Demonstrates |
| --- | --- |
| [`Components/RevenueMetric.php`](../examples/Components/RevenueMetric.php) | Extending `Foundation\NamedComponent`, a unique type and fluent property schema. |
| [`Building/RevenueMetricBuilder.php`](../examples/Building/RevenueMetricBuilder.php) | A typed `money()` method selected by `builderClass()`. |
| [`Values/Money.php`](../examples/Values/Money.php) | An immutable `ValueObject` with scalar normalization output. |
| [`Configurations/Padding.php`](../examples/Configurations/Padding.php) | A non-renderable configuration node with a validated value. |

Example classes are outside production autoloading; the example declaration
explicitly loads them. In an application, place them under the corresponding
`App\...` namespaces and use Composer's application autoloading. Keep the package
provider registered; custom classes still use its manager and services.

The example registers `revenueMetric` for the current scope. In production,
register aliases through your application's provider using an `afterResolving`
callback, so registrations return after request/job scope resets. See
[custom catalog registrations](component-catalog.md#custom-registrations).

Schema rules and property normalizers belong in the component definition;
fluent configuration behavior belongs in its builder. Rendering and action
execution belong in consuming packages. The [extension guide](extension-guide.md)
details these boundaries.
