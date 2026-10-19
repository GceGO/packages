<?php

declare(strict_types=1);

namespace Gcego\Components\Support;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Property;

/**
 * The constructor items of a component, separated by kind.
 */
final readonly class ParsedArguments
{
    /**
     * @param  list<Property>  $properties
     * @param  list<ComponentInterface>  $children
     * @param  list<ConfigurationNode>  $configurations
     */
    public function __construct(
        public array $properties,
        public array $children,
        public array $configurations,
    ) {}
}
