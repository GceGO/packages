# Design tokens

Chroma owns the **non-color** tokens. They are CSS custom properties, `--gcego-<name>`, declared in `resources/css/tokens.css` for `:root` (light) and `:root.dark`, and exposed to Tailwind by `resources/css/theme.css` through `@theme inline`:

| Token | Type | Light | Dark | Utilities |
| --- | --- | --- | --- | --- |
| radius-sm | length | 0.375rem | 0.375rem | `rounded-gcego-sm` |
| radius | length | 0.5rem | 0.5rem | `rounded-gcego` |
| radius-lg | length | 0.75rem | 0.75rem | `rounded-gcego-lg` |
| shadow-card | shadow | soft 2-layer | deeper, black | `shadow-gcego-card` |
| shadow-overlay | shadow | soft 2-layer | deeper, black | `shadow-gcego-overlay` |
| font-sans | font family | system UI stack | same | `font-gcego` |
| duration | duration | 150ms | 150ms | `var(--gcego-duration)` |

`Gcego\Chroma\Tokens\DefaultTokens` is the source of truth, and `tests/Unit/TokensTest.php` fails when `tokens.css` disagrees. Values are validated by `TokenType` grammars (length, shadow, font family, duration), which exclude `;`, `{`, `}`, `<`, `>`, `\` and `url()`.

## Colors

Color tokens (`surface`, `foreground`, `primary`, `border`, `ring`, the status and chart colors …) belong to **gcego/swatches**: see `docs/swatches/SEMANTIC-TOKENS.md` at the repository root. `gcego.css` imports Swatches, so `bg-surface`, `text-foreground`, `bg-gcego-surface` and `text-gcego-text-muted` all work. A color token in a Chroma theme is refused (`InvalidTokenException::colorToken`).
