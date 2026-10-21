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

    /** Identifiers such as permission abilities or panel ids: "view", "subjects.export", "admin-panel". */
    public const string IdentifierPattern = '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/';

    public const string IconPattern = '#^[a-z0-9][a-z0-9._:/-]*$#';

    /** A fully qualified PHP class name, without a leading backslash. */
    public const string ClassPattern = '/^[A-Za-z_\\x80-\\xff][A-Za-z0-9_\\x80-\\xff]*(?:\\\\[A-Za-z_\\x80-\\xff][A-Za-z0-9_\\x80-\\xff]*)*$/';

    public const int MaxNameLength = 100;

    public const int MaxTitleLength = 200;

    public const int MaxDescriptionLength = 1000;

    public static function isName(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= self::MaxNameLength && preg_match(self::NamePattern, $value) === 1;
    }

    public static function isIdentifier(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= self::MaxNameLength && preg_match(self::IdentifierPattern, $value) === 1;
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
     * Non-empty text without control characters other than line breaks and tabs.
     */
    public static function isDescription(mixed $value): bool
    {
        return is_string($value)
            && trim($value) !== ''
            && mb_strlen($value) <= self::MaxDescriptionLength
            && preg_match('/[^\P{Cc}\t\r\n]/u', $value) === 0;
    }

    /**
     * A syntactically valid class name such as "App\\Models\\Subject".
     *
     * Only the spelling is checked: the class is never autoloaded.
     */
    public static function isClassName(mixed $value): bool
    {
        return is_string($value) && preg_match(self::ClassPattern, $value) === 1;
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
