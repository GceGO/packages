# Routing

## Registration

With `routing.enabled` (the default), the provider registers page routes in an `app->booted()` callback, after the application's routes, unless routes are cached:

```
GET {prefix}/{path}   name: {name_prefix}{route}   middleware: routing.middleware + Page::Middleware
```

The registrar reads `PageRouteDefinition`s from the registry index (descriptor metadata written when the page was registered); it never loads a page definition. Each route's action is `PageController` plus the page identity under the `gcego_page` key, so `php artisan route:cache` serializes it. Measured: 311 pages register in about 5 ms.

## Conflicts

`PageRouteConflictException` is thrown at boot for a route name already in use or a GET URL already answered (parameter names do not matter: `admin/subjects/{subject}` and `admin/subjects/{record}` collide), whether by an application route or another page.

## Custom registration

Set `routing.enabled` to `false` in `config/gcego-pages.php`, then register the routes from a service provider or a routes file:

```php
app(PageRouteRegistrar::class)->register(
    app('router'),
    prefix: 'school',
    namePrefix: 'school.',
    middleware: ['web', 'auth', 'gcego.tenant:required'],
    domain: 'school.example.com',
    filter: fn (PageRouteDefinition $route, DefinitionDescriptor $page): bool => $page->scope() === 'school',
);
```

## URLs

```php
app(PageManager::class)->url('subjects.edit', 7);                         // "/admin/subjects/7/edit"
app(PageManager::class)->url('institutions.members', null, ['institution' => 1]);
```

`PageRouteResolver` finds routes by their `gcego_page` action, so it works with cached routes and custom prefixes; it returns null for pages without a route or parameters that do not fit.
