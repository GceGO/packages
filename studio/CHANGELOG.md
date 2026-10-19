# Changelog

## Unreleased - 2026-10-18

- With gcego/sentinel installed, every panel route also gets `sentinel.two-factor` (required two-factor authentication), next to `sentinel.active`.
- Authentication is gcego/sentinel's job, or the application's own.

## Unreleased

- **Security:** with gcego/sentinel installed and enabled, every panel route also runs `sentinel.active`, so an account suspended or disabled mid-session is signed out of all panels on its next request (Sentinel guarded only its own pages).
- Tests: an authentication journey through gcego/sentinel (dev dependency).

## 0.1.0 - 2026-10-18

First release.

- **Entry point:** `Studio` (`StudioInterface`) over panels, modules, pages, packages, capabilities, health, compatibility, bootstrap, inspection and extensions. `StudioManager` resolves Studio's services lazily.
- **Integration:** `PackageIntegrationManager` checks each of the eighteen foundation packages — installed, provider loaded, registered with gcego/core, booted — and detects the optional packages (notifications, media, workflows, settings, analytics, testing, cli, sentinel).
- **Bootstrap:** `StudioBootstrapper` applies trusted extensions after boot, before panel routes are built, and `verify()` returns a `BootstrapResult`.
- **Configuration:** `config/gcego-studio.php` and a typed, validated `StudioConfiguration`.
  - `StudioDefaults` feeds the documented gcego/panels appearance and asset settings, and registry discovery toggles, only where set.
  - No value can loosen authentication, authorization or tenant isolation.
- **Installer:** `php artisan gcego:install`, with `--panel`, `--model`, `--module`, `--dry-run`, `--force` and `--no-interaction`.
  - It creates the configuration, a starter panel guarded by a Gate ability, a dashboard and an optional read-only example module.
  - It is idempotent, asks before overwriting, writes atomically, never writes outside the application or through symlinks, and creates no users or permissions.
  - Stubs can be published and customized.
- **Health:** `php artisan gcego:studio:health [--production] [--json]` runs eleven built-in checks plus production readiness (caches, debug, HTTPS, session, autoloader, built assets) and extension checks. Exceptions become failed checks, and the command exits with 1 on failure.
- **Compatibility:** `PackageCompatibilityChecker` checks PHP, Laravel and Livewire minimums and every installed GCEGO package's `require` constraints.
- **Assets:** `StudioAssetManager` inspects Vite entries, the manifest, the dev server, the Tailwind v4 and gcego/chroma imports, and duplicate Alpine.
- **Inspector:** `php artisan gcego:studio:inspect [--json]`, built on the panels, modules, pages and navigation inspectors, with production redaction.
- **Extensions:** the `StudioExtension` contract contributes health checks, installation steps, gcego/panels panel extensions and inspector sections, with unique ids, deterministic order and no late registration.
