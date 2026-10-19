# Types, casting, defaults and nulls

| `SettingType` | PHP value returned | Accepted input (strict) |
| --- | --- | --- |
| `String` | string | strings; integers and finite floats as decimal text |
| `Integer` | int | ints, digit strings (`"-12"`), floats without a fraction |
| `Float` | float | ints, finite floats, numeric strings |
| `Boolean` | bool | bools, `1`/`0`, `true/false`, `yes/no`, `on/off`, `1/0` text (any case) |
| `Array` | array | arrays, JSON text of an array or object |
| `Json` | null, scalar or array | arrays, scalars, null; strings are JSON text and must be valid |
| `Date` | `CarbonImmutable` (midnight UTC) | `DateTimeInterface`, a real `Y-m-d` date |
| `DateTime` | `CarbonImmutable` (UTC) | `DateTimeInterface`, ISO 8601, `Y-m-d H:i[:s]`, `Y-m-d\TH:i` |
| `Enum` | the backed enum case | a case, or a backing value (`"5"` for int-backed enums) |
| `Media` | string (lowercase ULID) | a gcego/media ULID (ownership checked, see validation) |

Anything else is refused with a reason code (`InvalidSettingException::$reason`)
and a translated message. `3.5` is never stored as `3`, and `"maybe"` is never `false`.
Arrays may hold only null, scalars and arrays, at most 16 levels deep.

**Storage** is JSON text, never PHP serialization. Reading decodes the JSON
and casts it strictly again, so a stored value that no longer fits its
definition is skipped, logged, and listed by the inspector. It is never
returned with the wrong type.

**Defaults** are static values in the definition. When nothing is stored at
any level, the default applies (typed: a Date default `"2026-09-01"` is a
`CarbonImmutable`).

**Missing, null and empty are different.** A missing row falls back to the
next level. An explicitly stored `null` (a nullable setting) is a value at
its level: `resolve()->isOverridden()` is true, and nothing falls back.
An empty string is a value of a String setting.
