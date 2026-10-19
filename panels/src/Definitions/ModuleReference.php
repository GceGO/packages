<?php

declare(strict_types=1);

namespace Gcego\Panels\Definitions;

use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Modules\Module;

/**
 * Includes a registered gcego/modules module in a panel: all its pages are
 * routed under the panel and its resources appear in the panel's navigation.
 *
 *     new ModuleReference('academics')
 *
 * A reference names a module; it never copies it. Module, resource and
 * record authorization still apply inside the panel.
 */
final class ModuleReference extends Component
{
    public function module(): string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : '';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('panels.module')
            ->withoutProperties(self::Label, self::Description, self::Id)
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && preg_match(Module::NamePattern, $value) === 1, ruleDescription: 'a module name such as "academics"'))
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }
}
