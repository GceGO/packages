<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * A container resembling a future table: a model shorthand plus typed children.
 *
 *     new ParentComponent(FakeModel::class, new ExampleComponent('name', 'Name'));
 */
final class ParentComponent extends Component
{
    public const string Model = 'model';

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.parent')
            ->withProperties(new PropertyDefinition(self::Model, Type::classString(FakeModel::class)))
            ->withAllowedChildren([ExampleComponent::class, ChildComponent::class], min: 1, max: 3)
            ->withAllowedConfigurations([Style::class])
            ->withConstraint(static function (ComponentInterface $component): array {
                $seen = [];
                $messages = [];

                foreach ($component->children() as $child) {
                    $name = $child->get(self::Name);

                    if (! is_string($name)) {
                        continue;
                    }

                    if (isset($seen[$name])) {
                        $messages[] = sprintf('Child name "%s" is used more than once.', $name);
                    }

                    $seen[$name] = true;
                }

                return $messages;
            });
    }

    protected static function shorthand(): array
    {
        return [self::Model];
    }
}
