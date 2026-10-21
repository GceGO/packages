<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

use Gcego\Components\Component;
use Gcego\Components\Properties\Property;

final class ConstructorComponent extends Component
{
    public readonly bool $initialized;

    public function __construct(string $name, mixed ...$items)
    {
        parent::__construct(new Property(self::Name, $name), ...$items);
        $this->initialized = true;
    }

    public static function builderClass(): string
    {
        return ConstructorComponentBuilder::class;
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }
}
