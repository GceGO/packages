# Authorization

`SettingAccessPolicy` (bound as `Gcego\Settings\Contracts\SettingsAuthorizer`)
denies by default, and any error also denies.

| Target | Allowed when |
| --- | --- |
| any | a person is signed in, and the setting allows the scope |
| global, panel | the setting's requirements (its own, its section's and its group's) pass, **or** the Gate ability `gcego-settings.manage` allows |
| tenant | a verified membership of that tenant **and** (the membership grants `settings.manage`, **or** the requirements pass inside the tenant) |
| user | it is the person's own preference (and the requirements, if any, pass) |

- Viewing a setting needs the same rights as changing it. Sensitive values
  are never shown to anyone, only whether one is set.
- Pages also apply their own requirements. A page appears only when the
  viewer can see at least one of its settings.
- Export and import also need `gcego-settings.export` /
  `gcego-settings.import`. For a tenant, the membership must grant
  `settings.manage` and the export or import ability, either as a tenant
  permission or as a Gate ability.
- Every write is authorized again, inside `SettingsWriter`, for every key
  and before anything is validated. Livewire forms do not trust their own
  earlier checks.

## Trusted code

Seeders, deployments and commands have no signed-in person. They must name
themselves:

```php
$settings->global()->asSystem('deploy')->set('maintenance_mode', true);
$settings->forUser($user)->actingAs($admin)->set('page_size', 50);   // authorized as $admin
```

A system actor skips person authorization. It never skips validation,
scope rules or history. Without a person and without `asSystem()`, writes
are refused. No configuration option turns authorization off:
`security.require_authorization`, `encrypt_sensitive_values` and
`enforce_tenant_scope` must stay true, and the application fails to boot
otherwise.

## Security-sensitive settings

Settings that affect access (session policies, institution access) are
platform-only (`scopes: ['global']`). Declare them with explicit
requirements (`new Ability('security.manage')`) and, when needed, an
approval workflow. Stored settings do not replace Laravel's own
enforcement: read them in your middleware or policies, which still decide.
