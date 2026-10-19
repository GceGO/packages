# Extension guide: a component with its own renderer

Everything below is a working fixture in `tests/Fixtures/` and is exercised by
`tests/Feature/BlueprintRenderingTest.php`.

## 1. The component (declarative only)

```php
// tests/Fixtures/ExampleTable.php
final class ExampleTable extends Component implements BlueprintPart
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.table')
            ->withAllowedChildren([ExampleParagraph::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::single(BlueprintRole::Table);
    }
}
```

The component contains no rendering code.

## 2. The renderer

```php
// tests/Fixtures/TableRenderer.php
final class TableRenderer extends BladeRenderer
{
    protected function view(): string
    {
        return 'fixtures::table';
    }
}
```

Override `data()` for extra view variables, and `childHeadingLevel()` when the
renderer outputs a heading.

## 3. The Blade view

```blade
{{-- tests/Fixtures/views/table.blade.php --}}
<table data-path="{{ $context->path() }}">
    <caption>{{ $component->get('label', $component->name()) }}</caption>
    <tbody>
        @foreach ($children as $child)
            <tr><td>{{ $child }}</td></tr>
        @endforeach
    </tbody>
</table>
```

## 4. Registration (your package's service provider)

```php
public function boot(): void
{
    $this->loadViewsFrom(__DIR__.'/../resources/views', 'fixtures');
    $this->app->make(RendererRegistry::class)->register(ExampleTable::class, TableRenderer::class);
}
```

The tests do the same in `tests/TestCase.php`.

## 5. Use

```php
$blueprint = new Blueprint(
    'subjects',
    new ExampleTable('table', new ExampleParagraph('Mathematics'), new ExampleParagraph('Physics')),
);

echo app(Runtime::class)->render($blueprint);
```

## 6. Output (whitespace collapsed; ids shortened)

```html
<section id="gcego-…" aria-labelledby="gcego-…-title" data-gcego-blueprint="subjects">
  <header><h1 id="gcego-…-title">Subjects</h1></header>
  <div>
    <table data-path="subjects.table"><caption>table</caption><tbody>
      <tr><td><p data-path="subjects.table.#0">Mathematics</p></td></tr>
      <tr><td><p data-path="subjects.table.#1">Physics</p></td></tr>
    </tbody></table>
  </div>
</section>
```

## Checklist for component packages

- **Renderers:** one per component class, or per interface for families.
  Keep them stateless.
- **Escaping:** print every dynamic value with `{{ }}`; reserve
  `RenderResult::trustedHtml()` for markup you wrote.
- **Accessibility:** use semantic elements, give inputs labels, follow the
  heading levels from `$context->headingLevel` and `nextHeadingLevel()`, use
  `$context->domId()` for `id` and `aria-*` references, and do not nest
  interactive elements.
- **Styling:** expose slots through `$styles->attribute('slot')`; leave classes
  to gcego/chroma.
- **Livewire state:** implement `StatefulComponent` and bind with
  `$context->wireModel()` only when `$context->isLivewire()`.
- **Inspection:** `RuntimeInspector::inspect($definition)` lists the
  components that still lack a renderer.
