# Encryption and sensitive settings

```php
new Setting(name: 'external_api_secret', default: null, encrypted: true);
new Setting(name: 'sms_api_key', default: null, sensitive: true);   // also encrypted
```

- **At rest:** the JSON payload is encrypted with Laravel's encrypter (your
  `APP_KEY` and cipher). This is authenticated encryption, so a tampered
  value fails to decrypt and is never returned. Key rotation uses
  `APP_PREVIOUS_KEYS`.
- **Reads:** only `get()` and `resolve()` from trusted server code decrypt.
  Never pass the result to a view, a response or a log.
- **Forms:** a `PasswordInput` that is never hydrated. "A value is set" is the
  only thing shown. Leaving it empty keeps the value; typing replaces it.
  Secrets never enter Livewire state.
- **History, events, notifications, logs:** they record that a sensitive
  setting changed (`redacted`, `rotated`), never a value, not even encrypted.
- **Cache:** the cache holds the stored ciphertext only.
- **Export:** sensitive settings are never exported. They are listed under
  `redacted`. Imports refuse them.

## Rotation

```php
$settings->global()->actingAs($admin)->rotate('external_api_secret', $newSecret, $expectedVersion);
```

Rotation is authorized like any write. It refuses empty secrets and
settings that are not sensitive, records a `rotated` history entry,
dispatches `SecretRotated` (without values) and invalidates the cache.
External secret vaults are out of scope.
