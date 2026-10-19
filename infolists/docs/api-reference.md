# API reference

Namespace `Gcego\Infolists`.

## Infolist

`Infolist` (BlueprintPart, role `infolist`).

**Properties:**

| Property | Default | Meaning |
| --- | --- | --- |
| `Name` | `infolist` | the infolist's name |
| `Label`, `Description` | | heading and text |
| `Columns` | `layouts.default_columns` | top-level entry grid |
| `WithTrashed` | `false` | also find soft-deleted records |
| `EmptyHeading` | | empty-state text |

**Children:**
- entries and layouts;
- gcego/actions `Action`s.

**Configuration nodes:**
- `Gcego\Chroma\Tailwind`;
- gcego/security `Requirement`s.

**Slot constants:** `Section`, `SectionHeader`, `SectionBody`, `Entry`,
`EntryLabel`, `EntryValue`, `EntryDescription` and `EmptyState`. The
slots that are registered are listed in [styling](styling.md).

## Entries, layouts and configuration

See [entries](entries.md) and [layouts](layouts.md).

| Group | Classes |
| --- | --- |
| Entries | `Entries\Entry` (abstract) and its subclasses `Text`, `Number`, `Money`, `Boolean`, `Badge`, `Icon`, `Image`, `Date`, `DateTime`, `Link`, `KeyValue`, `ListEntry`, `RepeatableEntry` |
| Layouts | `Layout\LayoutComponent` (abstract) and its subclasses `Section`, `Grid`, `Tabs`, `Tab`, `Split` |
| Configuration nodes | `Format(Closure\|class-string<EntryFormatter>)`, `Resolve(Closure\|class-string<ValueResolver>)`, `Access(?Requirement $read, bool $mask = false)`, `VisibleWhen($path, $value = true)`, `::filled()`, `::blank()`, `::not()`, `::using(Closure)` |

## InfolistManager (singleton)

| Method | Returns |
| --- | --- |
| `resolve(string $definition)` | `Resolution\ResolvedInfolist` (`definition`, `blueprint`, `infolist`, `path`, `source`, `schema`) |
| `authorize(ResolvedInfolist)` | `void`; throws `AuthorizationException` |
| `record(ResolvedInfolist, string\|int $key)` | the record; 404 / 403 |
| `render(string $definition, string\|int $key)` | `HtmlString` |
| `renderRecord(ResolvedInfolist, mixed $record, ?string $hostId = null, bool $live = false)` | `HtmlString` |
| `access(InfolistSchema, mixed $record)` | `Resolution\EntryAccess` (`level()`, `canReadPath()`, `readable()`) |
| `source(ResolvedInfolist)` | the Source with eager loads (cached) |
| `relations(ResolvedInfolist)` | `array<path, class-string<Model>>` |
| `model(ResolvedInfolist)` | `?class-string<Model>` |
| `schema(Infolist)` | `Schema\InfolistSchema` (`entries`, `relations`, `paths()`, `pathOf()`) |
| `inspect(string\|Infolist)` | `Diagnostics\InfolistReport` |

## Contracts

| Interface | Methods |
| --- | --- |
| `InfolistComponent` | marker for entries and layouts |
| `EntryFormatter` | `format(mixed $value, mixed $record, Entry $entry): string\|int\|float\|null` |
| `ValueResolver` | `resolve(mixed $record): mixed` |

## Other classes

| Class | Purpose |
| --- | --- |
| `Livewire\InfolistHost` | alias `gcego-infolist` |
| `Rendering\InfolistRenderer`, `EntryRenderer`, `LayoutRenderer` | runtime renderers |
| `Rendering\InfolistRenderContext`, `InfolistRenderFrame`, `Groups` | render state |
| `Support\SafeHtml` | `html()`, `markdown()` |
| `Presets\InfolistPresets` | literal classes |
| `Diagnostics\InfolistInspector` | inspection |

Diagnostic checks:
- `schema`, `source`, `paths`, `hidden`, `authorization`;
- `renderer`, `styling`, `html`, `media`, `performance`.

## Configuration (`config/gcego-infolists.php`)

| Key | Default |
| --- | --- |
| `formatting.empty_placeholder` | `—` |
| `formatting.date_format` | null (locale medium) |
| `formatting.datetime_format` | null (locale medium) |
| `formatting.timezone` | null (app.timezone) |
| `layouts.default_columns` | 2 |
| `lists.max_items` | 100 |
| `media.temporary_url_minutes` | 5 |

The prompt's `rendering.escape_html` switch was left out on purpose:
escaping is not optional.

## Exceptions

`Exceptions\InvalidEntryException` (definition errors) implements
`Exceptions\InfolistException`. Record failures are gcego/security
`AccessDeniedException` (404 or 403).

## Service provider

`InfolistsServiceProvider` (`gcego/infolists` package descriptor):
- publish tags `gcego-infolists-config` and `gcego-infolists-views`;
- views, translations and anonymous components under `gcego-infolists::`.
