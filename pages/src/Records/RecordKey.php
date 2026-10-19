<?php

declare(strict_types=1);

namespace Gcego\Pages\Records;

/**
 * The shapes a {record} route parameter may take. They become route
 * constraints (anything else is 404 before a page is even resolved) and are
 * checked again for Livewire hosts. A well-formed key grants nothing: the
 * record is still looked up in scope and authorized.
 */
final class RecordKey
{
    /**
     * @var array<string, string>
     */
    public const array Patterns = [
        'id' => '[0-9]{1,20}',
        'uuid' => '[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}',
        'ulid' => '[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}',
        'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'key' => '[A-Za-z0-9][A-Za-z0-9_-]*',
    ];

    public const int MaxLength = 191;

    public static function pattern(string $kind): string
    {
        return self::Patterns[$kind] ?? self::Patterns['key'];
    }

    public static function valid(string $kind, string $key): bool
    {
        return $key !== '' && strlen($key) <= self::MaxLength && preg_match('/^'.self::pattern($kind).'$/D', $key) === 1;
    }
}
