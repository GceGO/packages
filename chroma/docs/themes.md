# Themes

A theme name is shared by two packages:

- **gcego/swatches** owns its colors (light, dark, inheritance, presets `midnight`, `slate`, `ocean`, `forest`): see `docs/swatches/THEMES.md`;
- **gcego/chroma** owns its styling: non-color token overrides and preset overrides.

Every Swatches theme is a known Chroma theme (with no styling overrides unless you register some), so `ChromaManager::theme('ocean')`, presets and slot resolution work for any color theme.

## Configuration

```php
// config/gcego-chroma.php
'theme' => null,            // null follows gcego-swatches.theme
'themes' => [
    'ocean' => [
        'tokens' => ['radius' => '1rem', 'radius-lg' => '1.25rem'],
        'dark' => ['shadow-card' => 'none'],
        'presets' => ['surface.card' => ['rounded-3xl']],
    ],
],
```

```php
$chroma->registerTheme(new Theme('ocean', tokens: ['radius' => '1rem'], presets: [new Slot('surface.card', ['rounded-3xl'])]));
```

`<x-gcego-chroma::theme name="ocean" />` / `ChromaManager::themeTag()` prints the theme's runtime CSS: the Swatches colors when they are not in the static stylesheet, followed by Chroma's non-color overrides. It prints nothing when neither is needed.
