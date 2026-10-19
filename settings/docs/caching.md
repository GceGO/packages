# Caching and long-running workers

`SettingsCache` caches, per scope instance (global, one panel, one tenant,
one user), the stored rows exactly as they are in the database (sensitive
payloads stay encrypted). Effective values are assembled from those
entries on every read. When a global value changes, every inherited
tenant and user value changes with it.

## Keys and invalidation

`<prefix>:<generation>:scope:<scope>:<sha256(scope key)>:<stamp>`

- Each scope instance has a *stamp*. A write changes the stamp after its
  transaction commits. A reader reads the stamp first, then the entry for
  that stamp. A reader that loaded old rows can only store them under the
  old stamp, which nobody reads any more, so stale values are never served
  after the write is visible.
- `flushCache()` / `php artisan gcego:settings:clear-cache` change the
  generation, which invalidates everything on every worker.
- Scope keys are hashed, so no tenant or user ids appear in cache key
  names and no two scope keys collide.

Use a shared store (`gcego-settings.cache.store`: redis, database,
memcached) when several servers run. Cache errors fall back to the database.

## Queries

A read loads all the scope instances it needs in one query on a cold
cache, and makes no query on a warm one. A group of 200 settings costs one
query.

## Long-running workers

Request memory lives in scoped services (never in static state), and
Laravel forgets them after every request and queued job, Octane included.
No settings package code keeps a tenant, a user or a value between them.
