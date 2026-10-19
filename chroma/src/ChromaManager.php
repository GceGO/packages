<?php

declare(strict_types=1);

namespace Gcego\Chroma;

use Gcego\Chroma\Classes\ClassList;
use Gcego\Chroma\Classes\ClassMerger;
use Gcego\Chroma\Presets\PresetRegistry;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Chroma\Slots\SlotResolver;
use Gcego\Chroma\Themes\Theme;
use Gcego\Chroma\Themes\ThemeRegistry;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Swatches\Branding\Brand;
use Gcego\Swatches\SwatchesManager;
use Gcego\Swatches\Themes\Accent;
use Gcego\Swatches\Themes\AppearanceMode;
use Illuminate\Support\HtmlString;

/**
 * The styling entry point for packages and templates that are not runtime renderers.
 *
 * Stateless apart from the framework-wide registries it wraps; the active
 * theme or appearance of a request is always passed in, never stored. Color,
 * appearance and branding calls delegate to gcego/swatches (swatches()).
 */
final readonly class ChromaManager
{
    public function __construct(
        private SlotResolver $resolver,
        private SlotRegistry $slots,
        private PresetRegistry $presets,
        private ThemeRegistry $themes,
        private ChromaConfig $config,
        private ClassMerger $merger,
        private SwatchesManager $swatches,
    ) {}

    public function classes(ComponentInterface $component, string $slot, ?string $theme = null): string
    {
        return $this->resolver->resolve($component, $slot, $theme)->toString();
    }

    public function classList(ComponentInterface $component, string $slot, ?string $theme = null): ClassList
    {
        return $this->resolver->resolve($component, $slot, $theme);
    }

    /**
     * A preset with the theme's overrides applied.
     */
    public function preset(string $id, ?string $theme = null): ClassList
    {
        $classes = $this->presets->get($id);

        foreach ($this->themes->get($theme ?? $this->defaultTheme())->presetOverrides($id) as $override) {
            $classes = $override->applyTo($classes, $this->merger);
        }

        return $classes;
    }

    /**
     * Merge class lists left to right with ClassMerger semantics: `merge('p-4 rounded', ['p-6'])` gives "p-6 rounded".
     *
     * @param  string|list<string>|ClassList  ...$lists
     */
    public function merge(string|array|ClassList ...$lists): string
    {
        $result = ClassList::empty();

        foreach ($lists as $list) {
            $result = $result->merge(ClassList::from($list), $this->merger);
        }

        return $result->toString();
    }

    /**
     * Declare the slots a component supports (for component packages).
     *
     * @param  class-string  $component
     * @param  list<SlotDefinition>  $slots
     */
    public function defineSlots(string $component, array $slots, bool $inherit = true, bool $override = false): void
    {
        $this->slots->register($component, $slots, $inherit, $override);
    }

    /**
     * Application-wide overrides for a component class or interface.
     *
     * @param  class-string  $component
     */
    public function override(string $component, Slot ...$slots): void
    {
        $this->slots->override($component, ...$slots);
    }

    /**
     * @param  string|list<string>|ClassList  $classes
     */
    public function definePreset(string $id, string|array|ClassList $classes, bool $override = false): void
    {
        $this->presets->register($id, $classes, $override);
    }

    public function registerTheme(Theme $theme, bool $override = false): void
    {
        $this->themes->register($theme, $override);
    }

    /**
     * The styling theme (non-color tokens and preset overrides); colors come from gcego/swatches.
     */
    public function theme(?string $name = null): Theme
    {
        return $this->themes->get($name ?? $this->defaultTheme());
    }

    public function defaultTheme(): string
    {
        return $this->config->theme;
    }

    /**
     * The color system (themes, appearance, accents, tenant branding).
     */
    public function swatches(): SwatchesManager
    {
        return $this->swatches;
    }

    /**
     * Attributes for the <html> element: `<html {{ $styling->rootAttributes() }}>`.
     */
    public function rootAttributes(?string $theme = null, ?Brand $brand = null): HtmlString
    {
        return $this->swatches->rootAttributes($theme ?? $this->explicitDefault(), $brand);
    }

    public function appearance(): AppearanceMode
    {
        return $this->swatches->appearance();
    }

    public function allowsSwitching(): bool
    {
        return $this->swatches->allowsSwitching();
    }

    /**
     * The main colors users may pick, or none when accents are off.
     *
     * @return array<string, Accent>
     */
    public function accents(): array
    {
        return $this->swatches->accents();
    }

    public function accentTag(?string $theme = null, ?string $nonce = null): HtmlString
    {
        return $this->swatches->accentTag($theme ?? $this->explicitDefault(), $nonce);
    }

    public function brandTag(?Brand $brand = null, ?string $theme = null, ?string $nonce = null): HtmlString
    {
        return $this->swatches->brandTag($brand, $theme ?? $this->explicitDefault(), $nonce);
    }

    public function storageKey(): string
    {
        return $this->swatches->storageKey();
    }

    public function appearanceScript(): string
    {
        return $this->swatches->appearanceScript();
    }

    public function appearanceTag(?string $nonce = null): HtmlString
    {
        return $this->swatches->appearanceTag($nonce);
    }

    /**
     * The Alpine.js form controls (listbox, date / time picker) shared by gcego/forms and gcego/tables.
     */
    public function controlsScript(): string
    {
        return (string) file_get_contents(ChromaServiceProvider::path('resources/js/controls.js'));
    }

    /**
     * The inline <script> registering the shared controls. Safe to emit more than once: the script registers itself once per page.
     */
    public function controlsTag(?string $nonce = null): HtmlString
    {
        return new HtmlString(sprintf(
            '<script data-gcego-controls%s>%s</script>',
            $nonce === null || $nonce === '' ? '' : ' nonce="'.e($nonce).'"',
            $this->controlsScript(),
        ));
    }

    /**
     * The <style> with a theme's custom-property overrides: its colors (gcego/swatches) and
     * its non-color tokens; nothing when the static stylesheets already cover the theme.
     * Token values are validated (TokenType grammars, Swatches color parsing), so they are safe inside <style>.
     */
    public function themeTag(?string $theme = null, ?string $nonce = null): HtmlString
    {
        $name = $this->swatches->activeTheme($theme ?? $this->explicitDefault());
        $css = $this->swatches->themeCss($name).$this->theme($name)->css();

        if ($css === '') {
            return new HtmlString('');
        }

        return new HtmlString(sprintf(
            '<style data-gcego-theme-styles%s>%s</style>',
            $nonce === null || $nonce === '' ? '' : ' nonce="'.e($nonce).'"',
            $css,
        ));
    }

    /**
     * The chroma default when it differs from Swatches' (so a tenant brand preset may still apply otherwise).
     */
    private function explicitDefault(): ?string
    {
        return $this->config->theme === $this->swatches->defaultTheme() ? null : $this->config->theme;
    }
}
