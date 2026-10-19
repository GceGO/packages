# Formatting

Formatting is shared with gcego/tables (`Gcego\Tables\Support\Formats`), so
tables and record pages read the same way.

| Value | How it is shown |
| --- | --- |
| Text | escaped; enums show `label()` or their value; lists are joined |
| Numbers | intl `NumberFormatter` in the app locale (or `Locale`) |
| Money | ISO 4217 currency formatting, with the currency's own digits (XAF 0, EUR 2) unless `Decimals` is set. `MinorUnits` divides with bcmath. Formatting never calculates. |
| Dates | `Date` stays on its calendar day. `DateTime` converts from `app.timezone` to the entry's `Timezone`, then `formatting.timezone`. Both work on immutable copies and render inside `<time datetime>`. |
| Default formats | `formatting.date_format` / `datetime_format` (PHP formats), or the locale's medium format when null |
| Booleans | icon plus text (Yes/No, or custom labels) |

## Rich text

```php
new Text('syllabus', 'Syllabus', new Property(Text::Markdown, true))
new Text('body', 'Body', new Property(Text::Html, true))
```

| Mode | How it is made safe |
| --- | --- |
| Markdown | Rendered by league/commonmark with raw HTML stripped and unsafe links disallowed, then sanitized. |
| HTML | Sanitized by symfony/html-sanitizer against an allow-list (`Support\SafeHtml::Elements`): no scripts, styles, iframes or event attributes; links limited to http, https, mailto and relative; `rel="noopener noreferrer"` is forced. |

There is no raw-HTML entry.

## Custom text and values

```php
new Text('score', 'Score', new Format(fn (?int $score, Result $result) => $score === null ? null : $score.' / '.$result->max));
new Text('grade', 'Grade', new Format(GradeFormatter::class));            // Contracts\EntryFormatter
new Number('average', 'Average', new Resolve(fn (Student $s) => $s->results->avg('score')));
new Text('summary', 'Summary', new Resolve(StudentSummary::class));       // Contracts\ValueResolver
```

Formatters return text, which is escaped. Resolvers receive the
authorized record with its eager-loaded relations.
