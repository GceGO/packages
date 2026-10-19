# Installation

## Composer

```bash
composer require gcego/core
```

### Inside the GCEGO monorepo

The packages are not published yet. Point Composer at them with path
repositories in the consuming application's `composer.json`:

```json
{
    "repositories": [
        { "type": "path", "url": "packages/components" },
        { "type": "path", "url": "packages/core" }
    ],
    "require": {
        "gcego/core": "@dev"
    }
}
```

Composer symlinks the directories, and the version reports as `dev-<branch>`.

## Laravel integration

`composer.json` declares the provider for package discovery:

```json
"extra": {
    "laravel": {
        "providers": ["Gcego\\Core\\GcegoServiceProvider"]
    }
}
```

If the application disables discovery, register the provider explicitly in
`bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    Gcego\Core\GcegoServiceProvider::class,
];
```

Registering it twice is harmless: Laravel keeps a single instance.

## Publishing the configuration

```bash
php artisan vendor:publish --tag=gcego-config
```

This copies `config/gcego.php` into the application. You only need to publish
when you want to change values; unpublished keys fall back to the package
defaults.

## Verifying the installation

```php
app(Gcego\Core\Gcego::class)->diagnose()->isHealthy(); // true
```

See [diagnostics](diagnostics.md).
