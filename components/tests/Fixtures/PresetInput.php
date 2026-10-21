<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Forms\TextInput;
use Gcego\Components\Schema\ComponentDefinition;

/**
 * A text input whose setUp() hook preconfigures every make() builder.
 */
final class PresetInput extends TextInput
{
    public const string MaxLength = 'maxLength';

    public const string Placeholder = 'placeholder';

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('fixtures.presetInput');
    }

    protected static function setUp(ComponentBuilder $builder): ComponentBuilder
    {
        return $builder->set(self::MaxLength, 255)->set(self::Placeholder, 'preset');
    }
}
