# Changelog

## Unreleased

- Typed icons: `Gcego\Luminaries\Icons\Heroicon`, `HeroiconSolid`, `HeroiconMini`, `HeroiconMicro` and `Lucide` (one case per installed icon, values `provider:icon`), plus `Alias` (the framework and education aliases).
- `Contracts\IconReference` for application enums of icon names. `IconName::of()` turns a name or a string-backed enum into an identifier.
- `Luminaries::exists|resolve|tryResolve|render|html|svg`, `<x-gcego-icon :name>` and `<x-gcego-icon-button :icon>` accept enums. Int-backed enums are refused as invalid names.
- `gcego:icons:enums` regenerates the enums deterministically from the installed sets. `--check` fails when they are out of date, and the test suite runs it.
- gcego/morfaw stores a string-backed enum given to a string property as its value, so icon properties of every package take these enums.

## 0.1.0 - 2026-10-18

First release.

- Providers over Blade Icons: `heroicon`, `heroicon-solid`, `heroicon-mini`, `heroicon-micro`, `lucide`. Also trusted custom SVG collections and inline path icons.
- `IconName` grammar (`provider:icon`, `group.alias`, bare names) and `IconRegistry` with duplicate, cycle and depth checks.
- Semantic aliases: `FrameworkPreset` (182 aliases, used by every framework screen) and the optional `EducationPreset` (24), with application overrides.
- `<x-gcego-icon>` and `<x-gcego-icon-button>`: size and tone tokens, `currentColor`, stroke width for stroke-drawn providers, WCAG 2.2 AA markup, attribute allowlist.
- Missing-icon behaviors: exception (with diagnostics and suggestions), fallback, placeholder, empty.
- Security: `TrustedSvgValidator`, `PathGuard` (trusted roots, no traversal or symlink escape), `AttributeGuard`.
- `gcego:icons:providers|list|search|check|cache|clear`. Cache and clear are wired into `php artisan optimize`.
- Icon manifest keyed by configuration fingerprint. Blade Icons per-icon components disabled by default.
- Developer icon explorer, off by default and gated by `viewLuminariesExplorer`.
- Built-in `inline:circle`. Duplicate classes are removed when rendering.
- Framework integration: all package templates use semantic aliases. Toast icons render server-side through Luminaries. A monorepo guard test enforces both.
