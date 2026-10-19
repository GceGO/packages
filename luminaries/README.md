# GCEGO Luminaries

Centralized icon management for GCEGO Admin Studio. Luminaries puts one API over Heroicons, Lucide, trusted custom SVG collections and inline path icons, using Blade Icons as the rendering layer. It adds semantic aliases, size and tone tokens, accessible markup, a trusted-source SVG policy, a cached manifest, Artisan tooling and a gated developer explorer.

```blade
<x-gcego-icon name="actions.edit" />
<x-gcego-icon name="lucide:graduation-cap" size="lg" tone="primary" />
<x-gcego-icon name="status.warning" tone="warning" label="Needs review" />
<x-gcego-icon-button icon="actions.delete" label="Delete subject" wire:click="delete" />
```

```php
use Gcego\Luminaries\Facades\Luminaries;

Luminaries::exists('actions.edit');
Luminaries::resolve('status.danger')->canonical();   // "heroicon:x-circle"
```

Typed icons: every installed icon and semantic alias is an enum case, so names autocomplete and typos fail before they render:

```php
use Gcego\Luminaries\Icons\Heroicon;   // also HeroiconSolid, HeroiconMini, HeroiconMicro, Lucide
use Gcego\Luminaries\Icons\Alias;      // the framework and education aliases

new Property(Resource::Icon, Heroicon::AcademicCap);
new Property(Resource::Icon, Alias::NavigationStudents);   // still follows config('luminaries.aliases')
Luminaries::render(Lucide::House, size: 'lg');
```

```blade
<x-gcego-icon :name="\Gcego\Luminaries\Icons\Lucide::GraduationCap" />
```

An application's own aliases go in a string-backed enum that implements `Gcego\Luminaries\Contracts\IconReference`; it is accepted everywhere the bundled enums are. The enums are generated from the installed sets: run `php artisan gcego:icons:enums` after upgrading an icon package, and `--check` in CI.

Change an icon everywhere from `config/luminaries.php`:

```php
'aliases' => ['actions.edit' => 'lucide:pencil'],
```

```bash
php artisan gcego:icons:providers
php artisan gcego:icons:list [provider] [--group=actions]
php artisan gcego:icons:search academic [--provider=lucide]
php artisan gcego:icons:check
php artisan gcego:icons:cache | gcego:icons:clear
php artisan gcego:icons:enums [--check]
```

Full documentation is in [`docs/luminaries`](../../docs/luminaries): architecture, installation, configuration, providers, semantic aliases, custom icons, accessibility, security, integration and testing.

## License

MIT. Heroicons (MIT) and Lucide (ISC) artwork is provided by their Blade Icons packages.
