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

## Closures (Filament style)

```php
Infolist::make()->schema([
    Section::make('Details')->columns(2)->schema([
        Text::make('name')->tooltip(fn (Subject $record): string => $record->code),
        Text::make('summary')->state(fn (Subject $record): string => $record->name.' ('.$record->code.')'),
        Text::make('credits')->formatStateUsing(fn (?int $state): string => $state === null ? 'n/a' : $state.' credits'),
        Text::make('status')->hidden(fn (?User $user): bool => ! $user?->can('viewStatus')),
    ]),
    Section::make('Pricing')->visible(fn (Subject $record): bool => $record->credits > 1)->schema([...]),
    RepeatableEntry::make('topics')->schema([
        Text::make('title')->formatStateUsing(fn (string $state, Topic $record, Subject $root): string => $root->code.' / '.$state),
    ]),
]);
```

| Injected | Value |
| --- | --- |
| `$record` | the record being shown (the item inside a repeatable entry), also by model type |
| `$root` | the infolist's record, also inside repeatable items |
| `$state` | the entry's value (`state()` and `formatStateUsing()`, and presentation properties) |
| `$entry`, `$user` | the entry, and the current user |

Results are checked against the property (a `tooltip` closure must return a
string or bool) and escaped. A masked entry only resolves its label. Records
are never built by the container: a closure that asks for a model type that is
not in the context fails clearly instead of receiving an empty model.

## Extending another module's infolist

```php
Components::extend('subjects.infolist', fn (SchemaPatch $patch) => $patch
    ->insertAfter('name', Text::make('short_code'))
    ->remove('internal_code'));
```

## Custom entries

Extend `Entry` and implement:
- `view()`;
- optionally `format()` or `display()`.

The registered `EntryRenderer` covers subclasses. The view receives:
- `$cell` (from `display()`), `$entry`, `$placeholder`;
- `$icons`, `$presets` and `$tablePresets`.
