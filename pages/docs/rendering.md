# Rendering

`PageRenderer` is the gcego/runtime renderer of every `Page` (registered for the class, inherited by the types). It renders `gcego-pages::pages.page`:

```html
<div data-gcego-page="page:subjects-view" aria-labelledby="…-title" class="mx-auto … max-w-7xl">
  <header>
    <nav aria-label="Breadcrumb">…</nav>
    <h1 id="…-title">Chemistry</h1>
    <p>Subject Chemistry</p>
    <div role="group" aria-label="Page actions">…links and action hosts…</div>
  </header>
  <div>…the page's components, in order…</div>
</div>
```

- The page title is the only `h1`; children start at `h2` (layouts pass heading levels down).
- The Source is not rendered; actions not in the header belong to their table or form.
- Children whose own requirements deny the user are left out; when nothing is left, the empty state (`Empty`) shows.
- `?tab=name` opens that tab of the page's tab sets (the page is rendered as a copy with `Tabs::Active` set; paths are unchanged).

Pages render only by registry identity: `PageManager::render()`, the page route, `PageHost`, or `RenderEngine::render('page:…')` (which opens and authorizes the page first). A Page rendered without an identity is refused, because its parts could not be trusted.

## Views

`gcego-pages::pages.page`, `partials.breadcrumbs`, `components.content`, `components.link`, `livewire.host`, `states.missing` and `layouts.app`; publish them with `--tag=gcego-pages-views`.

## Styling

Slots: `root`, `header`, `title`, `description`, `icon`, `breadcrumbs`, `actions`, `body`, `empty`, `loading` (`Page::styleSlots()`), merged with the defaults through gcego/chroma (`ChromaManager::merge`). Unknown slots fail in strict styling mode and are reported by the inspector. `Content` and `PageLink` have `root` (and `icon`). Defaults live in `Presets\PagePresets`, which gcego/chroma scans for its stylesheet.
