# Accessibility

## Static checks

<!-- example:accessibility-guide -->
```php
use Gcego\Testing\GcegoTest;

GcegoTest::accessibility(<<<'HTML'
    <h1>Subjects</h1>
    <label for="q">Search</label><input id="q">
    <input aria-label="Name" aria-invalid="true" aria-describedby="name-error"><p id="name-error">Required.</p>
    <table><thead><tr><th scope="col">Name</th></tr></thead><tbody><tr><td>Algebra</td></tr></tbody></table>
    HTML)->assertAccessible();

GcegoTest::accessibility('<h1>A</h1><h3>B</h3>')->assertViolates('heading-order');
GcegoTest::accessibility('<h1>A</h1><h3>B</h3>')->assertAccessible(except: ['heading-order']);
```

| Rule | Checks |
| --- | --- |
| `form-label` | inputs, selects and textareas have an accessible name |
| `image-alt` | images have `alt` (empty for decorative ones) |
| `button-name`, `link-name` | buttons and links have an accessible name |
| `heading-order`, `empty-heading` | heading levels never skip forward; headings are not empty |
| `table-headers` | data tables have header cells |
| `dialog-name` | dialogs are named (`aria-labelledby`, `aria-label`) |
| `duplicate-id`, `aria-reference` | ids are unique; ARIA references point at existing ids |
| `error-association` | invalid fields reference their error message |
| `positive-tabindex` | no positive `tabindex` |
| `aria-hidden-focus` | nothing focusable inside `aria-hidden="true"` |
| `landmark-name` | repeated `nav` or `aside` landmarks have distinct names |

Accessible names are computed in the order browsers use: `aria-labelledby`, `aria-label`, an associated `<label>`, then content (including image `alt`), then `title`. Every rendered result (`html()`, `render()`) has `accessibility()` and `assertAccessible()`.

## Browser checks

`assertAccessibleInBrowser($tags, $except)` loads the markup in headless Chromium (Playwright) and runs axe-core, by default with the `wcag2a` and `wcag2aa` tags:

```bash
npm install --save-dev playwright axe-core
npx playwright install chromium
```

- **Finding the tools**: the toolkit finds `node_modules` by walking up from the application's base path. Set `gcego-testing.accessibility.node_modules` to choose one.
- **When the tools are missing**, the audit throws `TestEnvironmentException`; it never passes silently:

```php
it('passes axe', function () {
    GcegoTest::accessibility($html)->assertAccessibleInBrowser();
})->skip(fn () => ! GcegoTest::manager()->browser()->available(), 'Browser tools are not installed.');
```

- **What none of this replaces**: neither static nor axe checks prove a page is accessible. Keyboard and screen-reader testing still matter.
