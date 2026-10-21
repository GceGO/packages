<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures\Extensions;

use Gcego\Blueprints\Definitions\OptionsDefinition;

/**
 * Exposes fromOptions() with an untyped array, to test schema checks that typed make() methods prevent.
 */
final readonly class RawTheme extends OptionsDefinition
{
    public static function definitionKey(): string
    {
        return 'raw_theme';
    }

    public static function schema(): array
    {
        return Theme::schema();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function raw(array $options): self
    {
        return self::fromOptions($options);
    }
}
