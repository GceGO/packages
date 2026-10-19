# Configuration

`config/gcego-studio.php` is read once into `Gcego\Studio\Configuration\StudioConfiguration`, a readonly container singleton. Invalid values throw `StudioConfigurationException`, and the message names the key.

## Feeding package settings

Studio owns no engine setting. Where a key has an equivalent in a package, Studio writes it into that package's configuration just before the package reads it (on the first resolution of `PanelsConfig` or `RegistryConfig`), and only when the Studio value is not `null`:

| Studio key | Package setting |
| --- | --- |
| `appearance.default_layout` | `gcego-panels.appearance.default_layout` |
| `appearance.default_theme` | `gcego-panels.appearance.default_theme` |
| `assets.vite` | `gcego-panels.assets.vite` |
| `discovery.panels` = false | removes registry roots of kind `panel` |
| `discovery.modules` = false | removes registry roots of kind `module` (added by gcego/modules from `gcego-modules.discovery.paths`) |
| `discovery.pages` = false | removes registry roots of kind `page` |

Order of precedence: a definition's own setting (for example `Panel::Layout`), then the Studio value, then the package's default.

Because the values are applied to the configuration repository, `php artisan config:cache` captures them like any other configuration.

## Validation

| Key | Rule |
| --- | --- |
| `appearance.default_layout` | `sidebar`, `topbar`, `compact` or null |
| `appearance.default_theme` | lowercase name (`[a-z][a-z0-9-]*`) or null |
| `assets.vite` | null or a list of relative paths without `..` |
| `assets.css`, `js`, `manifest` | strings |
| `security.*`, `diagnostics.*`, `discovery.*`, `enabled` | booleans |
| `extensions` | classes implementing `StudioExtension` |
| `install.panel` | lowercase letters, digits and dashes |
| `install.ability` | a Gate ability name (letters, digits, `.`, `_`, `-`, `:`) |

## Environment variables

Studio reads none directly. If you want one, use `env()` in your published `config/gcego-studio.php`, never elsewhere, so `config:cache` keeps working.
