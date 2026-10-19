# Dark mode

Light, dark and system appearance, the appearance controller and user accents moved to **gcego/swatches**: see `docs/swatches/THEMES.md`. Chroma keeps thin delegations so existing layouts keep working:

```blade
<html {{ app(\Gcego\Chroma\ChromaManager::class)->rootAttributes() }}>
<head>
    <x-gcego-chroma::appearance :nonce="Vite::cspNonce()" />   {{-- same as <x-gcego-swatches::appearance> --}}
    <x-gcego-chroma::theme />
```

`ChromaManager::appearance()`, `allowsSwitching()`, `storageKey()`, `appearanceScript()`, `appearanceTag()`, `accents()` and `accentTag()` delegate to `SwatchesManager`. Configure them in `config/gcego-swatches.php` (`appearance.*`, `accents.*`). An old `gcego-chroma.appearance` or `gcego-chroma.accents` key is still honoured (with a deprecation notice) while the matching `gcego-swatches` section is at its defaults.

Dark values of Chroma's own tokens (`shadow-card`, `shadow-overlay`) live in `tokens.css` under `:root.dark`, and the `dark:` variant comes from Swatches' `tailwind.css`.
