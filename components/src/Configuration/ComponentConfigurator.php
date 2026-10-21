<?php

declare(strict_types=1);

namespace Gcego\Components\Configuration;

use Closure;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Component;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Patching\SchemaPatch;

/**
 * Application-wide component defaults (configureUsing), builder macros and
 * schema extensions.
 *
 * Registered as an application singleton: configuration declared in a
 * service provider's boot() survives Octane requests, while every
 * application instance (and so every test) starts clean.
 */
final class ComponentConfigurator
{
    /**
     * @var array<int, array{component: class-string<Component>, modifier: Closure, important: bool}>
     */
    private array $modifiers = [];

    /**
     * @var array<class-string<Component>, array<string, Closure>>
     */
    private array $macros = [];

    /**
     * @var array<string, list<Closure(SchemaPatch): SchemaPatch>>
     */
    private array $extensions = [];

    public function __construct()
    {
        Components::getFacadeRoot();
    }

    /**
     * Register a modifier applied to every builder of the class (and its
     * subclasses) created through make(). With $during, the modifier only
     * applies while that callback runs and the callback's result is returned.
     * Important modifiers run after the others.
     *
     * @param  class-string<Component>  $component
     * @param  Closure(ComponentBuilder): ComponentBuilder  $modifier
     */
    public function configureUsing(string $component, Closure $modifier, ?Closure $during = null, bool $isImportant = false): mixed
    {
        $this->modifiers[] = ['component' => $component, 'modifier' => $modifier, 'important' => $isImportant];

        if ($during === null) {
            return null;
        }

        $key = array_key_last($this->modifiers);

        try {
            return $during();
        } finally {
            unset($this->modifiers[$key]);
        }
    }

    /**
     * Apply the registered modifiers for the builder's component class.
     */
    public function configure(ComponentBuilder $builder): ComponentBuilder
    {
        $component = $builder->componentClass();

        foreach ([false, true] as $important) {
            foreach ($this->modifiers as $entry) {
                if ($entry['important'] !== $important || ! is_a($component, $entry['component'], true)) {
                    continue;
                }

                $configured = ($entry['modifier'])($builder);

                if (! $configured instanceof ComponentBuilder || $configured->componentClass() !== $component) {
                    throw InvalidBuilderException::invalidValue(
                        $component,
                        'configureUsing',
                        'Modifiers must return the configured builder, because builders are immutable: fn (ComponentBuilder $builder) => $builder->required().',
                    );
                }

                $builder = $configured;
            }
        }

        return $builder;
    }

    /**
     * @param  class-string<Component>  $component
     */
    public function macro(string $component, string $name, Closure $macro): void
    {
        $this->macros[$component][$name] = $macro;
    }

    /**
     * The macro for the class or its closest ancestor.
     *
     * @param  class-string<Component>  $component
     */
    public function macroFor(string $component, string $name): ?Closure
    {
        for ($class = $component; is_string($class); $class = get_parent_class($class)) {
            if (isset($this->macros[$class][$name])) {
                return $this->macros[$class][$name];
            }
        }

        return null;
    }

    /**
     * Register a patch for every schema published under the key.
     *
     * @param  Closure(SchemaPatch): SchemaPatch  $extension
     */
    public function extend(string $schema, Closure $extension): void
    {
        $this->extensions[$schema][] = $extension;
    }

    /**
     * @return list<Closure(SchemaPatch): SchemaPatch>
     */
    public function extensionsFor(string $schema): array
    {
        return $this->extensions[$schema] ?? [];
    }

    /**
     * @return array{modifierCount: int, macroCount: int, extensionCount: int}
     */
    public function __debugInfo(): array
    {
        return [
            'modifierCount' => count($this->modifiers),
            'macroCount' => array_sum(array_map(count(...), $this->macros)),
            'extensionCount' => array_sum(array_map(count(...), $this->extensions)),
        ];
    }
}
