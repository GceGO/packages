<?php

declare(strict_types=1);

namespace Gcego\Chroma;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Chroma\Classes\ClassMerger;
use Gcego\Chroma\Exceptions\InvalidThemeException;
use Gcego\Chroma\Presets\PresetRegistry;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Chroma\Slots\SlotResolver;
use Gcego\Chroma\Themes\Theme;
use Gcego\Chroma\Themes\ThemeRegistry;
use Gcego\Components\Component;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Styling\NullStylingResolver;
use Gcego\Swatches\Support\PreviewClasses;
use Gcego\Swatches\SwatchesConfig;
use Gcego\Swatches\SwatchesServiceProvider;
use Gcego\Swatches\Themes\ThemeRegistry as ColorThemes;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for gcego/chroma; also the "gcego/chroma" GCEGO package.
 *
 * Replaces gcego/runtime's NullStylingResolver with SlotResolver (unless the
 * application bound its own resolver), declares the slots of the runtime's
 * built-in renderers, and registers the gcego-chroma Blade components. Registers
 * gcego/swatches (colors) too, and gives its color explorer the real presets.
 * Nothing is scanned or compiled at runtime: CSS is built by Tailwind.
 */
final class ChromaServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Views = 'gcego-chroma';

    public function register(): void
    {
        // Colors are a hard dependency: register gcego/swatches when the application has not (no-op otherwise).
        $this->app->register(SwatchesServiceProvider::class);
        $this->mergeConfigFrom(self::path('config/gcego-chroma.php'), 'gcego-chroma');

        $this->app->singleton(ChromaConfig::class, static function (Application $app): ChromaConfig {
            $config = $app->make(Repository::class)->get('gcego-chroma', []);

            return ChromaConfig::fromArray(is_array($config) ? $config : [], $app->make(SwatchesConfig::class)->theme);
        });

        $this->app->singleton(ClassMerger::class);
        $this->app->singleton(PresetRegistry::class, static fn (): PresetRegistry => PresetRegistry::withDefaults());

        $this->app->singleton(ThemeRegistry::class, static function (Application $app): ThemeRegistry {
            $themes = new ThemeRegistry($app->make(ColorThemes::class));

            foreach ($app->make(ChromaConfig::class)->themes as $name => $definition) {
                $themes->register(Theme::fromConfig($name, $definition));
            }

            return $themes;
        });

        $this->app->singleton(SlotRegistry::class, static function (Application $app): SlotRegistry {
            $slots = new SlotRegistry;
            self::registerRuntimeSlots($slots);

            foreach ($app->make(ChromaConfig::class)->overrides as $target => $overrides) {
                if (! class_exists($target) && ! interface_exists($target)) {
                    throw InvalidThemeException::invalidConfig('overrides', sprintf('%s is not a class or interface.', $target));
                }

                foreach ($overrides as $slot => $value) {
                    $slots->override($target, self::overrideSlot($target, $slot, $value));
                }
            }

            return $slots;
        });

        $this->app->singleton(SlotResolver::class);
        $this->app->singleton(ChromaManager::class);

        $this->app->extend(StylingResolver::class, static fn (StylingResolver $resolver, Application $app): StylingResolver => $resolver instanceof NullStylingResolver
            ? $app->make(SlotResolver::class)
            : $resolver);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(self::path('resources/views'), self::Views);
        self::registerColorPreviews($this->app->make(PreviewClasses::class), $this->app->make(PresetRegistry::class));

        if ($this->app->runningInConsole()) {
            $this->publishes([self::path('config/gcego-chroma.php') => $this->app->configPath('gcego-chroma.php')], 'gcego-chroma-config');
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/chroma',
            requires: ['gcego/core', 'gcego/runtime', 'gcego/swatches'],
            capabilities: ['styling', 'styling.tailwind'],
            description: 'Tailwind CSS v4 styling: non-color design tokens, presets, slots and class merging (colors: gcego/swatches).',
        );
    }

    /**
     * The gcego/swatches color explorer previews components with these real presets.
     */
    public static function registerColorPreviews(PreviewClasses $previews, PresetRegistry $presets): void
    {
        $map = [
            'card' => 'surface.card',
            'button.primary' => 'control.button.primary',
            'button.secondary' => 'control.button.secondary',
            'button.danger' => 'control.button.destructive',
            'input' => 'control.input',
            'table' => 'data.table',
            'table.head' => 'data.table.head',
            'table.header-cell' => 'data.table.header-cell',
            'table.row' => 'data.table.row',
            'table.cell' => 'data.table.cell',
            'nav.item' => 'navigation.item',
            'tone.success' => 'tone.success',
            'tone.warning' => 'tone.warning',
            'tone.danger' => 'tone.danger',
            'tone.info' => 'tone.info',
        ];

        foreach ($map as $preview => $preset) {
            if ($presets->has($preset)) {
                $previews->set($preview, $presets->get($preset)->toString());
            }
        }
    }

    /**
     * Slots used by gcego/runtime's built-in BlueprintRenderer and ContainerRenderer views.
     */
    public static function registerRuntimeSlots(SlotRegistry $slots): void
    {
        $slots->register(BlueprintInterface::class, [
            new SlotDefinition(Tailwind::Wrapper, 'layout.section'),
            new SlotDefinition(Tailwind::Header, 'layout.header'),
            new SlotDefinition(Tailwind::Title, 'typography.title'),
            new SlotDefinition(Tailwind::Description, 'typography.description'),
            new SlotDefinition(Tailwind::Body, 'layout.body'),
        ]);

        $slots->register(Component::class, [
            new SlotDefinition(Tailwind::Wrapper, 'layout.stack'),
            new SlotDefinition(Tailwind::Title, 'typography.heading'),
            new SlotDefinition(Tailwind::Description, 'typography.description'),
        ], inherit: false);
    }

    private static function overrideSlot(string $target, string $slot, mixed $value): Slot
    {
        if (is_string($value) || (is_array($value) && array_is_list($value))) {
            /** @var string|list<string> $value */
            return new Slot($slot, $value);
        }

        if (is_array($value) && isset($value['classes'])) {
            $mode = SlotMode::tryFrom(is_string($value['mode'] ?? null) ? $value['mode'] : 'merge')
                ?? throw InvalidThemeException::invalidConfig('overrides.'.$target.'.'.$slot.'.mode', 'must be "merge", "replace" or "remove".');
            $classes = $value['classes'];

            if (! is_string($classes) && ! is_array($classes)) {
                throw InvalidThemeException::invalidConfig('overrides.'.$target.'.'.$slot.'.classes', 'must be a string or list of classes.');
            }

            /** @var string|list<string> $classes */
            return new Slot($slot, $classes, $mode);
        }

        throw InvalidThemeException::invalidConfig('overrides.'.$target.'.'.$slot, 'must be classes or ["mode" => ..., "classes" => ...].');
    }

    public static function path(string $relative): string
    {
        return dirname(__DIR__).'/'.$relative;
    }
}
