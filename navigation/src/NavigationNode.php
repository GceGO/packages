<?php

declare(strict_types=1);

namespace Gcego\Navigation;

use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Permissions\PermissionName;
use Gcego\Tables\Support\Icons;
use Illuminate\Support\Str;

/**
 * The base of every navigation node (items, groups, dropdowns, headings,
 * dividers, external links): an immutable gcego/morfaw definition.
 *
 * Visibility is decided per request from gcego/security requirements: the
 * node's own (Requirement nodes and the Permission property) and those of
 * every ancestor up to the Navigation. Nothing is authorized, routed or
 * queried at construction.
 */
abstract class NavigationNode extends Component
{
    public const string Icon = 'icon';

    /** Sort key among siblings (default 0); ties keep declaration order. */
    public const string Order = 'order';

    /** A gcego/security permission name required to see this node. */
    public const string Permission = 'permission';

    /** Hide the node (for example from configuration) without removing it. */
    public const string Hidden = 'hidden';

    public const string NamePattern = '/^[a-z0-9][a-z0-9_.-]{0,63}$/';

    /**
     * The node's identity: its Name, or its Label as a slug ("Academic Management" is "academic-management").
     */
    public function nodeName(): string
    {
        $name = $this->get(self::Name);

        if (is_string($name) && $name !== '') {
            return $name;
        }

        $label = $this->get(self::Label);

        return is_string($label) ? Str::limit(Str::slug($label), 64, '') : '';
    }

    /**
     * item, group, dropdown, heading, divider or external.
     */
    abstract public function nodeType(): string;

    public function label(): ?string
    {
        return $this->string(self::Label);
    }

    public function order(): int
    {
        $order = $this->get(self::Order);

        return is_int($order) ? $order : 0;
    }

    public function icon(): ?string
    {
        return static::definition()->hasProperty(self::Icon) ? $this->string(self::Icon) : null;
    }

    public function isHidden(): bool
    {
        return $this->get(self::Hidden) === true;
    }

    /**
     * Requirements of this node alone (its Requirement nodes and Permission property).
     *
     * @return list<Requirement>
     */
    public function requirements(): array
    {
        $requirements = array_values(array_filter($this->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));
        $permission = $this->string(self::Permission);

        return $permission === null ? $requirements : [...$requirements, new Permission($permission)];
    }

    protected function string(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('navigation.'.Str::kebab(class_basename(static::class)))
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'a lowercase identifier such as "students"'),
                new PropertyDefinition(self::Order, Type::int(), default: 0),
                new PropertyDefinition(self::Permission, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && PermissionName::isValid($value), ruleDescription: 'a permission name such as "students.view"'),
                new PropertyDefinition(self::Hidden, Type::bool(), default: false),
            )
            ->withAllowedConfigurations(static::nodeConfigurations());
    }

    /**
     * @return list<class-string<ConfigurationNode>>
     */
    protected static function nodeConfigurations(): array
    {
        return [Requirement::class, Tailwind::class];
    }

    protected static function iconProperty(): PropertyDefinition
    {
        return new PropertyDefinition(self::Icon, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Icons::NamePattern, $value) === 1, ruleDescription: 'an icon name');
    }
}
