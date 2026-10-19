# Entries

Every entry takes `(name, label)` shorthand or `Property` syntax. The name
is a trusted path into the record, written by the developer and never
taken from the browser:
- `name` reads an attribute;
- `category.name` reads through a relation;
- `tags.label` reads across a to-many relation.

## Common properties (`Entry::…`)

| Property | Meaning |
| --- | --- |
| `Label`, `Description` | label (`<dt>`) and helper text under the value |
| `Placeholder` | shown for empty values (default `formatting.empty_placeholder`, "—") |
| `Copyable` | copy-to-clipboard button for the shown value |
| `Tooltip` | fixed tooltip, or `true` for the full value |
| `Hidden`, `HiddenWhenEmpty` | never shown, or not shown without a value |
| `ColumnSpan` | 1 to 4, or `"full"`, inside entry grids |

Configuration nodes:
- `new Format(...)` for custom text;
- `new Resolve(...)` for a computed value;
- `new Access(read:, mask:)`;
- `new VisibleWhen(...)`;
- `new Tailwind(...)`.

## Types

| Entry | Properties | Notes |
| --- | --- | --- |
| `Text` | `Multiline`, `Limit`, `Markdown`, `Html`, `Monospace`, `Prefix`, `Suffix` | escaped by default; Markdown and HTML only through `SafeHtml` |
| `Number` | `Decimals`, `Grouping`, `Locale`, `Prefix`, `Suffix` | intl NumberFormatter |
| `Money` | `Currency` (default XAF), `MinorUnits`, `Decimals`, `Locale` | display only; minor units via bcmath |
| `Boolean` | `TrueLabel`, `FalseLabel`, `TrueIcon`, `FalseIcon` | icon plus visible text; null is the placeholder |
| `Badge` | `Variants`, `Labels`, `Icons`, `DefaultVariant` | values map to trusted variants (default, success, warning, danger, info, neutral), never to classes; lists show several badges |
| `Icon` | `Icons`, `Tones`, `Labels`, `Size` (sm/md/lg), `ShowLabel` | trusted icon set only |
| `Image` | `Disk`, `Private`, `Alt`, `AltColumn`, `Fallback`, `Round`, `Size` | lazy; private files only through temporary URLs |
| `Date` | `Format`, `Relative` | calendar dates, `<time datetime>` |
| `DateTime` | `Format`, `Relative`, `Timezone` | display zone conversion on an immutable copy |
| `Link` | `Route`, `RouteParameters`, `UrlColumn`, `Mailto`, `Text`, `NewTab`, `Icon` | http(s), mailto and relative URLs only |
| `KeyValue` | `Keys` (path → label, required), `AllowSensitive` | only declared keys; secret-looking keys refused unless allowed |
| `ListEntry` | `Style` (bulleted, numbered, badges, inline) | at most `lists.max_items` (100) items, with a "+N more" note |
| `RepeatableEntry` | `Columns`; children are entries | one group per item; stable keys; child paths are relative to the item |

```php
new KeyValue('settings', 'Settings', new Property(KeyValue::Keys, ['term' => 'Term', 'grading.scale' => 'Grading scale']));
new RepeatableEntry('topics', 'Topics', new Text('title', 'Topic'), new Text('teacher.name', 'Teacher'));
new Link('category.name', 'Category', new Property(Link::Route, 'categories.show'), new Property(Link::RouteParameters, ['category' => 'category_id']));
new Image('scan_path', 'Scan', new Property(Image::Disk, 's3'), new Property(Image::Private, true));
```

## Custom entries

Extend `Entry` and implement:
- `view()`;
- optionally `format()` or `display()`.

The registered `EntryRenderer` covers subclasses. The view receives:
- `$cell` (from `display()`), `$entry`, `$placeholder`;
- `$icons`, `$presets` and `$tablePresets`.
