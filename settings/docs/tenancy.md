# Tenancy and user preferences

Institutions are gcego/security tenants. One institution is one security
boundary and one scope (`tenant`); there is no separate institution scope.

- Tenant values are stored under `Tenant::id()`, so two tenant types never collide.
- Reads of `forTenant($x)` from trusted code resolve the tenant through your
  `TenantResolver` (an unknown tenant is a `SettingScopeException`).
- Settings screens take the tenant only from the request's verified tenant
  (gcego/security's `ResolveTenant` middleware). A tenant page without one is
  404. A form opened for institution 1 refuses requests whose session now
  points at institution 2 (403).
- Writes need a verified membership of that very tenant. The membership must
  grant `settings.manage` (configurable), or the person must pass the
  setting's requirements inside the tenant. A platform manager who is not a
  member cannot change an institution's values.
- Cache entries, history queries and exports are per scope instance. One
  institution never reads another's values, history or export.

## User preferences

Allow the `user` scope on a setting, and give people a page:

```php
new SettingsGroup('preferences',
    new Setting(name: 'preferred_theme', default: Theme::System, enum: Theme::class),
    new Setting(name: 'page_size', default: 25, options: [10 => '10', 25 => '25', 50 => '50']),
    scopes: ['global', 'user'],
);

new SettingsPage(name: 'preferences', title: 'My preferences', group: 'preferences', scope: 'user');
```

People may change only their own preferences. A user's effective value
inherits from the request's tenant and from the global level.
Notification preferences stay with gcego/notifications; this package does
not duplicate them.
