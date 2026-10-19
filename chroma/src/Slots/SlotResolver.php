<?php

declare(strict_types=1);

namespace Gcego\Chroma\Slots;

use Gcego\Chroma\ChromaConfig;
use Gcego\Chroma\Classes\ClassList;
use Gcego\Chroma\Classes\ClassMerger;
use Gcego\Chroma\Exceptions\InvalidSlotException;
use Gcego\Chroma\Exceptions\InvalidThemeException;
use Gcego\Chroma\Presets\PresetRegistry;
use Gcego\Chroma\Tailwind;
use Gcego\Chroma\Themes\ThemeRegistry;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Rendering\RenderContext;

/**
 * Resolves the effective classes of a component slot. Implements gcego/runtime's StylingResolver.
 *
 * Precedence, lowest first (each layer merges onto the previous one unless a
 * Slot says replace/remove):
 *  1. the framework preset named by the slot definition;
 *  2. the active theme's override of that preset;
 *  3. the component package's own slot classes;
 *  4. application overrides (config gcego-chroma.overrides and SlotRegistry::override());
 *  5. the component instance's Tailwind configuration nodes, in order.
 *
 * Layers 1-4 depend only on class, slot and theme, and are cached; the cache
 * key includes registry versions, so later registrations invalidate it.
 * Nothing request- or user-specific is cached.
 */
final class SlotResolver implements StylingResolver
{
    /**
     * @var array<string, ClassList>
     */
    private array $cache = [];

    public function __construct(
        private readonly SlotRegistry $slots,
        private readonly PresetRegistry $presets,
        private readonly ThemeRegistry $themes,
        private readonly ChromaConfig $config,
        private readonly ClassMerger $merger,
    ) {}

    public function classes(ComponentInterface $component, string $slot, RenderContext $context): string
    {
        return $this->resolve($component, $slot, $context->theme)->toString();
    }

    /**
     * @throws InvalidSlotException|InvalidThemeException
     */
    public function resolve(ComponentInterface $component, string $slot, ?string $theme = null): ClassList
    {
        $theme ??= $this->config->theme;
        $definitions = $this->slots->for($component);

        if ($definitions !== null) {
            $this->assertKnown($component, $slot, $definitions);
        }

        $key = implode('|', [$component::class, $slot, $theme, $this->slots->version(), $this->presets->version(), $this->themes->version()]);
        $classes = $this->cache[$key] ??= $this->staticLayers($component::class, $slot, $definitions[$slot] ?? null, $theme);

        foreach ($component->configurations()->ofClass(Tailwind::class) as $tailwind) {
            $classes = $tailwind->apply($slot, $classes, $this->merger);
        }

        return $classes;
    }

    private function staticLayers(string $class, string $slot, ?SlotDefinition $definition, string $theme): ClassList
    {
        $classes = ClassList::empty();

        if ($definition?->preset !== null) {
            $classes = $this->presets->get($definition->preset);

            foreach ($this->themes->get($theme)->presetOverrides($definition->preset) as $override) {
                $classes = $override->applyTo($classes, $this->merger);
            }
        } else {
            $this->themes->get($theme);
        }

        if ($definition !== null) {
            $classes = $classes->merge($definition->classes, $this->merger);
        }

        foreach ($this->slots->overridesFor($class, $slot) as $override) {
            $classes = $override->applyTo($classes, $this->merger);
        }

        return $classes;
    }

    /**
     * @param  array<string, SlotDefinition>  $definitions
     */
    private function assertKnown(ComponentInterface $component, string $slot, array $definitions): void
    {
        if (! $this->config->strict) {
            return;
        }

        $supported = array_keys($definitions);

        if (! isset($definitions[$slot])) {
            throw InvalidSlotException::unknown($slot, $component::class, $supported);
        }

        foreach ($component->configurations()->ofClass(Tailwind::class) as $tailwind) {
            foreach ($tailwind->slotNames() as $name) {
                if (! isset($definitions[$name])) {
                    throw InvalidSlotException::unknown($name, $component::class, $supported);
                }
            }
        }
    }
}
