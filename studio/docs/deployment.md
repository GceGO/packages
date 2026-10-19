# Deployment

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force          # migrations of the packages you use (e.g. tables, notifications)
php artisan optimize                 # config, route, event and view caches
php artisan gcego:registry-cache     # the definition index
php artisan gcego:studio:health --production
```

The last command exits with 1 on failure, so a pipeline can stop before switching traffic.

## Caches

- **config:cache:** Studio's defaults are written into the package configuration, so they are part of the cache. Run it again after changing `config/gcego-studio.php`.
- **route:cache:** panel routes (including extension routes) are compiled. gcego/panels skips route registration when routes are cached, so clear and rebuild the cache after adding a panel, module or page.
- **gcego:registry-cache:** rebuild after definition changes. `gcego:studio:health` reports a stale cache.

## Octane and queues

Studio's services hold no per-request state. Request-specific data (the user, tenant and panel) comes from the packages' scoped services, which Laravel resets per request.
