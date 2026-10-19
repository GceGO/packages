# Scopes, precedence and inheritance

| Scope | Scope key | Typical use |
| --- | --- | --- |
| `global` | `""` | platform configuration |
| `panel` | the panel name | panel branding, panel features |
| `tenant` | `Tenant::id()` (`institution:12`) | an institution's own settings |
| `user` | morph class and key | personal preferences |

A setting is stored only at the scopes it allows (`scopes`, or its group's,
else `global`). The configuration can switch a scope off for the whole
platform (`gcego-settings.scopes.*`).

## Reading

`$settings->get()` reads in the request's own context: the signed-in user,
the verified tenant, the verified panel, and the global level. Name a level
with `global()`, `forPanel('desk')`, `forTenant($institution)` or
`forUser($user)`. A read at a level sees that level and the less specific
known ones (user > tenant > panel > global). They are consulted in the
setting's precedence, and only at scopes the setting allows. The default
comes last.

```php
new Setting(name: 'preferred_theme', default: 'system', scopes: ['global', 'tenant', 'user']);
// user override → tenant override → global override → default

new Setting(name: 'enforced_locale', default: 'en', scopes: ['global', 'tenant', 'user'], precedence: ['tenant', 'user', 'global']);
// an institution's choice beats a person's
```

`resolve()` returns a `ResolvedSetting`: `value`, `source` (the level that
supplied it, or null for the default), `isOverridden()` and `isInherited()`,
and `version` (the stored version at the target, 0 when nothing is stored).

## Writing

Writes go to the named level. On the request's own context they go to the
most specific known level each setting allows. One write may not span
several levels; name the level in that case. `reset($key)` removes the
override at the level, and the setting inherits again (the definition stays).
