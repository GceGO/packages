# Discovery and registration

```php
// config/gcego-modules.php
'discovery' => [
    'enabled' => true,
    'paths' => [app_path('GCEGo/Modules')],
    'entrypoint' => '#(^|/)Module\.php$#',
],
```

```
app/GCEGo/Modules/
├── Academics/
│   ├── Module.php        ← entrypoint: returns a Module
│   ├── Subjects.php      ← supporting code: return new Resource(...); required by Module.php
│   └── Topics.php
└── Students/
    └── Module.php
```

- **Roots:** each path becomes a gcego/registry discovery root of kind `module`, so discovery is recursive, cached, checked for duplicates, lazy and limited to trusted paths. A `Module.php` may also return a `DefinitionProvider` class name.
- **The blueprint root:** keep resources out of folders named after themselves (`Subjects/Subjects.php`). The registry's default `blueprint` root also scans `app/GCEGo/Modules`, and treats such files as blueprint entrypoints.
- **Caching:** the registry caches the index; build it on deploy with `php artisan optimize`. Module pages are indexed from the module file, so changing a module (or its extensions) needs a rebuild unless `cache.verify` is on.

## Runtime registration

```php
app(ModuleManager::class)->register(new Module(...));   // pages are registered with it
```

Page routes are registered when the application boots. Register modules in a service provider, or turn `gcego-pages.routing.enabled` off and register routes yourself (`PageRouteRegistrar`).
