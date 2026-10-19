<?php

declare(strict_types=1);

namespace Gcego\Layouts;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\PartContainer;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Support\Absent;
use Gcego\Components\Type;
use Gcego\Layouts\Configuration\Align;
use Gcego\Layouts\Configuration\Gap;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;
use Gcego\Layouts\Responsive\Visibility;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Security\Contracts\Requirement;
use Gcego\Tables\Support\Icons;
use Illuminate\Support\Str;

/**
 * The base of every layout component: an immutable, declarative structure
 * around other GCEGO components.
 *
 * Layout components are blueprint part containers, so tables, forms,
 * infolists and actions nested in them still count as the blueprint's
 * parts. They never query, mount Livewire or render at construction; the
 * runtime renders them (LayoutRenderer) and each child keeps its own
 * renderer, lifecycle and authorization. Requirements (gcego/security) on
 * a layout component hide it, and everything in it, when they deny.
 */
abstract class LayoutComponent extends Component implements PartContainer
{
    public const string Icon = 'icon';

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::multiple(BlueprintRole::Layout);
    }

    /**
     * The Blade view (overridable in resources/views/vendor/gcego-layouts).
     */
    abstract public function view(): string;

    /**
     * Extra view data, from the rendered children.
     *
     * @return array<string, mixed>
     */
    public function viewData(LayoutView $layout): array
    {
        return [];
    }

    /**
     * Whether this component renders a direct child itself as one of its
     * regions (a tab, the main and aside of a split, header actions).
     */
    public function ownsRegion(self $child): bool
    {
        return false;
    }

    /**
     * Whether nothing is rendered when every child renders nothing (for
     * example when the user may see none of them).
     */
    public function hidesWhenEmpty(): bool
    {
        return true;
    }

    /**
     * Text shown instead of hiding when there is nothing to show.
     */
    public function emptyText(): ?string
    {
        return null;
    }

    public function childHeadingLevel(RenderContext $context): int
    {
        return $this->title() === null ? $context->headingLevel : $context->nextHeadingLevel();
    }

    /**
     * Classes placing a child in this layout (visibility here; grids add spans).
     * An empty string renders the child without a wrapper.
     */
    public function placement(ComponentInterface $child, int $siblings, LayoutsConfig $config): string
    {
        $visibility = $child->configurations()->first(Visibility::class);

        return $visibility instanceof Visibility ? trim(implode(' ', LayoutPresets::Item).' '.$visibility->classes()) : '';
    }

    /**
     * The styling slots of this component (name => what it styles).
     *
     * @return array<string, string>
     */
    public static function styleSlots(): array
    {
        return [Tailwind::Wrapper => 'The outer element.'];
    }

    public function title(): ?string
    {
        return $this->stringProperty(self::Label);
    }

    public function description(): ?string
    {
        return $this->stringProperty(self::Description);
    }

    public function icon(): ?string
    {
        return static::definition()->hasProperty(self::Icon) ? $this->stringProperty(self::Icon) : null;
    }

    /**
     * @return list<Requirement>
     */
    public function requirements(): array
    {
        return array_values(array_filter($this->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));
    }

    public function gapClasses(int $default): string
    {
        $gap = $this->configurations()->first(Gap::class);

        return $gap instanceof Gap ? $gap->classes($default) : LayoutPresets::Gaps['default'][$default];
    }

    public function alignClasses(string $default = ''): string
    {
        $align = $this->configurations()->first(Align::class);

        return $align instanceof Align ? $align->classes() : $default;
    }

    protected function stringProperty(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function boolProperty(string $key): bool
    {
        return $this->get($key) === true;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('layouts.'.Str::kebab(class_basename(static::class)))
            ->withAllowedConfigurations(static::layoutConfigurations());
    }

    /**
     * Configuration nodes this component accepts (placement nodes are always accepted).
     *
     * @return list<class-string<ConfigurationNode>>
     */
    protected static function layoutConfigurations(): array
    {
        return [Requirement::class, Tailwind::class];
    }

    protected static function iconProperty(): PropertyDefinition
    {
        return new PropertyDefinition(self::Icon, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Icons::NamePattern, $value) === 1, ruleDescription: 'an icon name');
    }

    /**
     * @param  list<string>  $values
     */
    protected static function choice(string $key, array $values, ?string $default = null): PropertyDefinition
    {
        return new PropertyDefinition($key, Type::string(), default: $default ?? Absent::Value, allowedValues: $values);
    }
}
