<?php

declare(strict_types=1);

namespace Gcego\Components\Foundation;

use Gcego\Components\Component;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * Shared name and shorthand contract for named components across all catalog categories.
 */
abstract class NamedComponent extends Component
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(new PropertyDefinition(
            self::Name,
            Type::string(),
            required: true,
            rule: static fn (mixed $value): bool => $value !== '',
            ruleDescription: 'non-empty string',
        ));
    }

    /** @return list<string> */
    protected static function shorthand(): array
    {
        return [self::Name];
    }
}
