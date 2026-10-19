# Validation and dependencies

Every write is validated on the server by `SettingsValidator` (bound as
`Gcego\Settings\Contracts\SettingValidator`):

1. strict casting to the type ([types](types.md));
2. Laravel validation of the typed value: `required` or `nullable`, the type
   rule, the field's implied rule (`email`, `url:http,https`), `Options`
   (`in`), the enum (`Rule::enum`), and the definition's own `rules`. Rule
   strings are trusted code; `ValidationRule` classes are resolved from the
   container;
3. media references must exist in the current tenant and be viewable by the
   signed-in user, through gcego/media;
4. dependencies, checked against the values as they would be after the
   change (stored values at the same level, overlaid with the submitted ones).

Messages are translated, and attribute names are the setting labels.
Errors come back as `InvalidSettingException::errors()` (key => messages)
or `->toValidationException()`.

## Dependencies

```php
new Setting(name: 'mail_enabled', default: false),
new Setting(name: 'mail_from_address', default: '', field: 'email', requiredWhen: ['mail_enabled' => true]),
new Setting(name: 'newsletter_enabled', default: false, requires: ['mail_enabled']),
```

- `requiredWhen`: the setting may not be empty while every named setting has
  the given value.
- `requires`: the setting may only be on (truthy) while every named setting
  is on. Turning `mail_enabled` off while `newsletter_enabled` is on is
  refused.

Dependencies are declared in PHP only, are checked for unknown keys and
cycles, and are evaluated on the server. Resets are validated too: the
inherited value must still satisfy the rules.

## Change effects

Side effects (refresh navigation, clear a derived cache, reconfigure an
integration) belong in listeners of `SettingUpdated` or `SettingsUpdated`.
These events are dispatched after the commit, carry no values, and include
a correlation id. Make the listeners idempotent.
