# Configuration

`config/gcego-panels.php` (publish with `--tag=gcego-panels-config`):

| Key | Default | Meaning |
| --- | --- | --- |
| `discovery.paths` | `[app_path('GCEGo/Panels')]` | folders whose PHP files return panels |
| `routing.name_prefix` | `gcego.` | prefix of every panel route name |
| `routing.middleware` | `['web']` | middleware every panel route starts with |
| `routing.register_page_routes` | `true` | panels route pages, and gcego/pages registers none of its own |
| `security.guard` | `null` | guard of panels that name none (`null`: the application default) |
| `appearance.default_layout` | `sidebar` | `sidebar`, `topbar` or `compact` |
| `appearance.default_theme` | `null` | a gcego/chroma theme |
| `navigation.collapsible_sidebar` | `true` | let users collapse the desktop sidebar |
| `extensions` | `[]` | `Contracts\PanelExtension` classes (packages usually register themselves) |
| `user_menu` | `[]` | label => route name links for every panel |
| `assets.vite` | `null` | Vite entries (`null`: `gcego-pages.rendering.vite`) |

Values are validated when first used (`InvalidPanelException`). The configuration is cacheable (`config:cache`). No option disables panel access checks.
