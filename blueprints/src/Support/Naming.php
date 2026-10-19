<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Support;

/**
 * Deterministic naming conventions for blueprints.
 *
 * Names are lowercase and therefore effectively case-sensitive without
 * ambiguity: "subjects" and "Subjects" can never both be valid.
 */
final class Naming
{
    public const string NamePattern = '/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$/';

    public const string SlugPattern = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const string IconPattern = '#^[a-z0-9][a-z0-9._:/-]*$#';

    public const int MaxNameLength = 100;

    public const int MaxTitleLength = 200;

    public static function isName(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= self::MaxNameLength && preg_match(self::NamePattern, $value) === 1;
    }

    public static function isSlug(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= self::MaxNameLength && preg_match(self::SlugPattern, $value) === 1;
    }

    public static function isIcon(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= self::MaxNameLength && preg_match(self::IconPattern, $value) === 1;
    }

    public static function isTitle(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= self::MaxTitleLength;
    }

    /**
     * "Subject Management" -> "subject-management", "academic_subjects" -> "academic-subjects".
     */
    public static function slugify(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
    }

    /**
     * "subjects" -> "Subjects", "academic-subjects" -> "Academic Subjects".
     */
    public static function humanize(string $name): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $name));
    }
}
