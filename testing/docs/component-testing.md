# Components, runtime and styling

## Components

`GcegoTest::component($component)` reads the component and never changes it:

<!-- example:component -->
```php
use Gcego\Components\Properties\Property;
use Gcego\Components\Validation\ErrorCode;
use Gcego\Forms\Fields\TextInput;
use Gcego\Testing\GcegoTest;

GcegoTest::component(new TextInput('name', 'Name', new Property(TextInput::Required, true)))
    ->assertValid()
    ->assertType(TextInput::class)
    ->assertName('name')
    ->assertLabel('Name')
    ->assertHasProperty(TextInput::Required)
    ->assertPropertyEquals(TextInput::Required, true)
    ->assertAcceptsValue(TextInput::MaxLength, 120)
    ->assertRejectsValue(TextInput::MaxLength, 'long')
    ->assertNormalized(['properties' => ['name' => 'name']]);

GcegoTest::component(new TextInput('name', 'Name', new Property(TextInput::MaxLength, 'long')))
    ->assertInvalid(ErrorCode::InvalidType);
```

- **Validity**:
  - `assertValid()` lists every error with its path.
  - `assertInvalid($code, $path)` narrows to an `ErrorCode` (or its value) and/or a path.
- **Labels**: `assertLabel()` uses the component's `label()` method when it has one (fields, columns, entries), otherwise the `label` property.
- **Property values**: `assertAcceptsValue()` and `assertRejectsValue()` ask the property definition (type, allowed values, rule). The component is not modified.
- **Defaults**: `assertDefault($key, $value)` checks the definition default, not an explicit value.
- **Required properties**: `assertRequiresProperty($key)` checks that the definition requires the property.

### Trees

Children are found by their `name` property or by a path relative to the component:

<!-- example:tree -->
```php
use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Testing\GcegoTest;

$dashboard = new Component(new Property(Component::Name, 'dashboard'), new Component(new Property(Component::Name, 'stats'), new Component(new Property(Component::Name, 'students'))));

GcegoTest::component($dashboard)
    ->assertChildCount(1)
    ->assertHasChild('stats')
    ->assertHasChild('stats.students')
    ->assertContainsType(Component::class, 3)
    ->assertNoDuplicateIds()
    ->child('stats')->assertChildCount(1);
```

## Runtime

<!-- example:runtime -->
```php
use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Rendering\Renderers\ContainerRenderer;
use Gcego\Testing\GcegoTest;

GcegoTest::runtime()
    ->render(new Component(new Property(Component::Label, 'Summary')))
    ->assertSeeText('Summary')
    ->assertAccessible();

GcegoTest::runtime()->assertRendererFor(new Component, ContainerRenderer::class);
```

- **Rendering**:
  - `render()` uses `Rendering::render()`, which does no authorization.
  - `renderAuthorized('blueprint:subjects')` is the `<x-gcego-rendering::render>` path: it authorizes first.
  - Both return an [HTML testable](#html).
- **Renderers**:
  - `assertMissingRenderer($tree, $class)` and `assertRenderable($tree)` read the runtime inspector.
  - `assertRenderFails($target, $exception, $message)` finds the exception anywhere in the `getPrevious()` chain, because Blade wraps view errors.

## Styling

Styling assertions check the classes `gcego/chroma` resolves through all five layers: preset, theme, slot definition, application overrides and instance `Tailwind` nodes.

<!-- example:styling -->
```php
use Gcego\Components\Component;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Testing\GcegoTest;

$card = new Component(new Tailwind('p-8', new Slot('title', 'text-2xl')));

GcegoTest::styling($card)
    ->assertHasClasses('root', 'p-8')
    ->assertLacksClasses('root', 'p-4')
    ->assertHasClasses('title', 'text-2xl');

GcegoTest::styling(new Component(new Tailwind(Slot::replace('root', 'grid gap-2'))))->assertClasses('root', 'gap-2 grid');
```

- **Order-insensitive**: `assertClasses()` compares sets.
- **Ordered**: `assertClassesInOrder()` exists for the rare case where order is contractual.
- **Other checks**:
  - `theme($name)` switches the theme for the following checks.
  - `assertUnknownSlot($slot)` expects strict styling to refuse an undeclared slot.
  - `assertHealthy()` runs the styling inspector on the component.

## HTML

`GcegoTest::html($html)` (and every `html()` / `render()` result) asserts rendered markup:

- **Text**:
  - `assertSee($text)` matches escaped text in the raw HTML.
  - `assertSeeText($text)` matches visible text only (`aria-hidden`, script and style are ignored).
  - `assertSeeInOrder([...])` matches visible text in order.
- **Elements and attributes**: `assertElement($selector, $count)` and `assertAttribute($selector, $attribute, $value)`.
- **Selectors**: XPath (starting with `/`), or a small CSS subset: `tag`, `#id`, `.class`, `[attr]`, `[attr="value"]`, combined, and descendants separated by spaces.
