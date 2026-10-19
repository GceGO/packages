# Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| `403` on every panel page | The panel ability is not defined or returns false. Define it with `Gate::define()`. |
| `404` for a tenant panel | You are not a member of that tenant (gcego/security), or the panel route is not cached yet. |
| Redirect to `/login`, then 404 | No `login` route. Install gcego/sentinel, or define one. |
| `packages.integrated` fails: "service provider … is not loaded" | The package is excluded by `dont-discover`. Register its provider. |
| "GCEGO failed to boot: … requires …" | A foundation package is missing: `composer require` it. |
| `routes.panels`: "collides with panel" | An application route uses a panel path. Rename one of them. |
| `assets.integration`: "not in the Vite manifest" | Run `npm run build`, or start `npm run dev`. |
| Unstyled panel | Import gcego/chroma's CSS in `resources/css/app.css`. |
| Alpine errors or double initialization | Remove your own `Alpine.start()`: Livewire 4 starts Alpine. |
| `StudioConfigurationException` | A value in `config/gcego-studio.php` is invalid; the message names it. |
| `ExtensionException: … after Studio applied extensions` | Register extensions in configuration or a provider, not after boot. |
| Changes to panels not visible in production | Rebuild the route and registry caches. |

`php artisan gcego:studio:inspect` shows what Studio sees, and `php artisan gcego:studio:health` explains each problem with a remediation.
