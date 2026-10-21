<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Properties\Property;

final class ConstructorComponentBuilder extends ComponentBuilder
{
    /**
     * @param  list<Property|ComponentInterface|ConfigurationNode>  $items
     */
    protected function instantiate(array $items): Component
    {
        $name = $this->propertyValue(Component::Name);

        if (! is_string($name)) {
            throw InvalidBuilderException::invalidValue($this->component, 'name', 'a string name is required');
        }

        $rest = array_values(array_filter($items, static fn ($item): bool => ! $item instanceof Property || $item->key !== Component::Name));

        return new ConstructorComponent($name, ...$rest);
    }
}
