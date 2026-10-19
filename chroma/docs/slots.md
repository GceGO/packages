# Slots and precedence

A **slot** is a named element of a component's markup that receives classes.
Renderers ask for them; gcego/chroma answers.

## Declaring slots (component packages)

```php
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Tailwind;

public function boot(): void
{
    $this->app->make(ChromaManager::class)->defineSlots(Card::class, [
        new SlotDefinition(Tailwind::Wrapper, 'surface.card'),
        new SlotDefinition(Tailwind::Header, 'layout.header', ['border-b', 'border-gcego-border', 'px-6', 'py-4']),
        new SlotDefinition(Tailwind::Title, 'typography.heading'),
        new SlotDefinition(Tailwind::Body, classes: ['p-6']),
    ]);
}
```

`SlotDefinition(name, ?preset, classes, ?description)`: the package classes
merge over the preset. Register for a class or an interface. Lookup order:

1. exact class;
2. nearest parent registered with `inherit: true` (the default);
3. first registered interface.

Registering the same target twice throws `StyleConflictException` unless you
pass `override: true`.

In the renderer's Blade view (gcego/runtime):

```blade
<article{{ $styles->attribute('root') }}>
    <header{{ $styles->attribute('header') }}>…</header>
    <div{{ $styles->attribute('body') }}>{{ $children }}</div>
</article>
```

The attribute is escaped and omitted when there are no classes. To combine with a
Blade `$attributes` bag, use `$attributes->class($styles('body'))`. This produces
a single `class` attribute.

## Built-in slots

| Target | Slots |
| --- | --- |
| `Gcego\Blueprints\Contracts\BlueprintInterface` | `root` → `layout.section`, `header` → `layout.header`, `title` → `typography.title`, `description` → `typography.description`, `body` → `layout.body` |
| `Gcego\Components\Component` (exact class only) | `root` → `layout.stack`, `title` → `typography.heading`, `description` → `typography.description` |

## Precedence

| # | Layer | Source | Cached |
| --- | --- | --- | --- |
| 1 | framework preset | `SlotDefinition::$preset` | yes |
| 2 | theme preset override | `Theme::$presets` | yes |
| 3 | component package | `SlotDefinition::$classes` | yes |
| 4 | application | `config('gcego-chroma.overrides')`, `ChromaManager::override()` | yes |
| 5 | instance | `Tailwind` nodes on the component, in order | no |

In layer 4, overrides apply least specific first: interfaces, then parent classes
(farthest first), then the class itself.

```php
app(ChromaManager::class)->override(Card::class, new Slot(Tailwind::Wrapper, ['shadow-none']));
```

```php
// config/gcego-chroma.php
'overrides' => [
    Gcego\Blueprints\Contracts\BlueprintInterface::class => [
        'root' => ['gap-8'],
        'title' => ['mode' => 'replace', 'classes' => ['text-3xl', 'font-bold']],
    ],
],
```

### Worked example (from `tests/Integration/ResolutionTest.php`)

```
1 surface.card    rounded-gcego-lg border border-gcego-border bg-gcego-surface text-gcego-text shadow-gcego-card
2 theme "brand"   rounded-3xl shadow-lg
4 app override    shadow-none p-2
5 instance        p-8
= rounded-3xl border border-gcego-border bg-gcego-surface text-gcego-text shadow-none p-8
```

## Merging rules

| Base | Override | Result |
| --- | --- | --- |
| `p-4` | `p-6` | `p-6` |
| `px-2 py-1` | `p-4` | `p-4` |
| `p-4` | `px-6` | `p-4 px-6` |
| `rounded-tl-lg` | `rounded-md` | `rounded-md` |
| `text-sm text-slate-600` | `text-red-700` | `text-sm text-red-700` |
| `border border-slate-200` | `border-2` | `border-2 border-slate-200` |
| `p-4 md:p-6` | `p-2` | `p-2 md:p-6` |
| `md:hover:p-4` | `hover:md:p-6` | `hover:md:p-6` |
| `p-4` | `p-6!` | `p-4 p-6!` |
| `custom-utility` | `bg-white` | `custom-utility bg-white` |

Use it directly: `app(ChromaManager::class)->merge('rounded-lg p-4', ['p-6'])`.

### Limits of merging

- **Coverage:** the utility table covers the families admin interfaces use, such
  as spacing, sizing, layout, flex/grid, typography, color, border, radius,
  shadow, ring, outline, opacity, transition and z-index. Utilities outside it
  are never removed, so two of them can coexist; use `Slot::replace` or
  `Slot::remove` then.
- **Custom colors:** a color name is recognised when it's a Tailwind palette
  color, a GCEGO token, or an arbitrary value. Custom `@theme` colors are
  recognised when you pass their names to `UtilityGroups`, for example
  `new UtilityGroups([...DefaultTokens::colorNames(), 'brand'])`.
- **Order:** CSS cascade order between different groups is unchanged.
