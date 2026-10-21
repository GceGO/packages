<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures\Extensions;

use Gcego\Blueprints\Definitions\Option;
use Gcego\Blueprints\Definitions\OptionsDefinition;

/**
 * Navigation placement, as gcego/navigation would contribute it:
 * Navigation::make(group: 'Academics', icon: 'book-open', sort: 2).
 */
final readonly class Navigation extends OptionsDefinition
{
    public const string Key = 'navigation';

    public static function definitionKey(): string
    {
        return self::Key;
    }

    public static function schema(): array
    {
        return [
            'group' => Option::text(required: true),
            'icon' => Option::icon(),
            'sort' => Option::integer(default: 0, min: 0, max: 1000),
            'visible' => Option::boolean(default: true),
            'badge' => Option::className(),
        ];
    }

    /**
     * @param  class-string|null  $badge
     */
    public static function make(string $group, ?string $icon = null, int $sort = 0, bool $visible = true, ?string $badge = null): self
    {
        return self::fromOptions(['group' => $group, 'icon' => $icon, 'sort' => $sort, 'visible' => $visible, 'badge' => $badge]);
    }
}
