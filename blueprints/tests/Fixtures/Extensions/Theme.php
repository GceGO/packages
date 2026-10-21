<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures\Extensions;

use Gcego\Blueprints\Definitions\Option;
use Gcego\Blueprints\Definitions\OptionsDefinition;

/**
 * A theme override, as gcego/chroma would contribute it: Theme::make('ocean', dark: true).
 */
final readonly class Theme extends OptionsDefinition
{
    public const string Key = 'theme';

    public static function definitionKey(): string
    {
        return self::Key;
    }

    public static function schema(): array
    {
        return ['name' => Option::identifier(required: true), 'dark' => Option::boolean()];
    }

    public static function make(string $name, bool $dark = false): self
    {
        return self::fromOptions(['name' => $name, 'dark' => $dark]);
    }
}
