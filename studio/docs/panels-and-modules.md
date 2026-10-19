# Panels, modules and pages

Studio does not define panels, modules or pages. It lists them through their managers and checks that they fit together.

- **Panels** (gcego/panels): files in `gcego-panels.discovery.paths` that return a `Panel`. `Studio::panels()` uses `PanelManager::names()`, and the inspector uses `PanelManager::inspect()`.
- **Modules** (gcego/modules): `Modules/<Name>/Module.php`. `Studio::modules()` uses `ModuleManager::names()`, and resource pages are derived into the registry.
- **Pages** (gcego/pages): registry kind `page`. `Studio::pages()` lists every page, including those derived from modules.
- **Navigation** (gcego/navigation): the inspector includes `NavigationManager::inspect()` for each panel.

## What the health checks look for

| Problem | Check |
| --- | --- |
| a panel without an access rule (`access.undeclared`) | `panels.access`: failure |
| module or page errors reported by their inspectors | `panels.access`: failure |
| two panels on one path, or an application route on a panel path | `routes.panels`: failure |
| a panel route without `auth` | `routes.panels`: failure |
| a `Tenant: required` panel with tenancy disabled | `security.tenancy`: failure |

## Multiple panels

Panels are independent. Access to one never implies another, each has its own path, guard, tenant mode, layout and branding, and the same module can appear in several panels with route names per panel (`gcego.<panel>.<module>.<resource>.<operation>`).
