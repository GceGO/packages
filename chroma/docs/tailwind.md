# The `Tailwind` node

`Gcego\Chroma\Tailwind` is an immutable configuration node. Add it to any
component, like a `Property`:

```php
new Card('Enrolments', new Tailwind('rounded-2xl', 'shadow-sm'));
```

## Forms

| Form | Meaning |
| --- | --- |
| `new Tailwind('rounded-2xl', 'border')` | shorthand: merged into the `root` (Wrapper) slot |
| `new Tailwind(['rounded-2xl', 'border'])` | the same, as a list |
| `new Tailwind('rounded-2xl border')` | the same, whitespace-separated |
| `new Tailwind(new Slot(Tailwind::Header, ['px-8']))` | a named slot |
| mixed | shorthand is collected into one Wrapper slot placed **first**, then slots in order |

Named arguments are rejected (`new Tailwind(wrapper: '…')` throws
`InvalidSlotException`), so slot names stay explicit.

## Slot names

`Tailwind::Wrapper` (`root`), `Header`, `Title`, `Description`, `Body`,
`Content`, `Footer`, `Label`, `Icon`, `Actions`. Components may declare others,
using lowercase letters, digits, `-`, `_` and `.`.

## Modes

```php
new Slot(Tailwind::Wrapper, ['p-8']);                      // merge (default)
Slot::replace(Tailwind::Header, ['flex', 'gap-2']);       // discard everything below this layer
Slot::remove(Tailwind::Wrapper, ['shadow-gcego-card']);   // remove exact classes
new Slot(Tailwind::Wrapper, 'p-2', SlotMode::Replace);    // explicit mode
```

Several slots for the same name apply in order, so remove-then-extend works:

```php
new Tailwind(
    Slot::remove(Tailwind::Wrapper, ['shadow-sm']),
    new Slot(Tailwind::Wrapper, ['ring-1', 'ring-slate-200']),
);
```

## Classes

- **Accepted:** variants, arbitrary values and arbitrary variants are kept as
  written, for example `dark:md:hover:text-white`, `data-[state=open]:rotate-180`,
  `grid-cols-[1fr_auto]`, `[&>*]:p-4`, `p-4!` and `@md:grid-cols-2`.
- **Rejected:** a class is rejected (`InvalidClassException`) when it is:
  - not a string;
  - longer than 200 characters;
  - contains whitespace inside brackets, `"`, `<`, a backtick, braces, a
    backslash or a control character;
  - has unbalanced brackets or an empty variant (`hover::x`).
- **Duplicates:** exact duplicates are removed, and the first one keeps its position.

### Write classes in full

Tailwind only generates classes it finds as literal text in scanned files.
`'md:grid-cols-'.$n` produces nothing. The build tests prove this
(`grid-cols-7` written literally is compiled; a concatenated `grid-cols-5` is
not). Choose between complete strings instead:

```php
$columns = match ($count) { 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3', default => 'md:grid-cols-4' };
```

Instance classes written in your application's own files are found by your
app's normal Tailwind sources (see [build integration](build-integration.md)).

## Unknown slots

When `gcego-chroma.strict` is true (the default), a `Tailwind` slot the
component doesn't declare throws:

```
Gcego\…\Card has no "heder" slot. Supported slots: root, header, title, body.
```

When it is false, the classes are still applied if a renderer asks for that slot.
Components that declare no slots at all are styled only by their `Tailwind` node.
