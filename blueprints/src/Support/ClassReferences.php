<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Support;

use Gcego\Blueprints\Exceptions\InvalidDefinitionException;

/**
 * Structural validation and normalization of class references.
 *
 * Only the spelling is checked; classes are never autoloaded here, so a
 * blueprint can be defined without loading (or even installing) the feature
 * classes it names. A leading backslash is removed.
 */
final class ClassReferences
{
    /**
     * @return class-string
     *
     * @throws InvalidDefinitionException
     */
    public static function one(string $key, mixed $class, ?string $position = null): string
    {
        $where = $position === null ? '' : $position.' ';

        if (! is_string($class)) {
            throw InvalidDefinitionException::because($key, sprintf('%sexpected a class name such as App\\Models\\Subject::class, got %s.', $where, get_debug_type($class)));
        }

        $normalized = ltrim(trim($class), '\\');

        if (! Naming::isClassName($normalized)) {
            throw InvalidDefinitionException::because($key, sprintf('%s"%s" is not a valid class name.', $where, $class), 'Pass a ::class constant such as SubjectsTable::class.');
        }

        /** @var class-string */
        return $normalized;
    }

    /**
     * A non-empty list of distinct class names, in declaration order.
     *
     * PHP class names are case-insensitive, so "App\Pages\ListSubjects" and
     * "app\pages\listsubjects" are duplicates.
     *
     * @param  array<mixed>  $classes
     * @return list<class-string>
     *
     * @throws InvalidDefinitionException
     */
    public static function list(string $key, array $classes): array
    {
        if (! array_is_list($classes)) {
            $keys = array_slice(array_map(static fn (int|string $index): string => var_export($index, true), array_keys($classes)), 0, 5);

            throw InvalidDefinitionException::because($key, sprintf('expected a list of class names, got an array keyed by %s.', implode(', ', $keys)), 'Remove the array keys: ::make([First::class, Second::class]).');
        }

        if ($classes === []) {
            throw InvalidDefinitionException::because($key, 'expected at least one class name.', 'Omit the definition instead of passing an empty list.');
        }

        $normalized = [];
        $seen = [];

        foreach ($classes as $index => $class) {
            $reference = self::one($key, $class, sprintf('element %d:', $index));
            $folded = strtolower($reference);

            if (isset($seen[$folded])) {
                throw InvalidDefinitionException::because($key, sprintf('%s is listed twice (elements %d and %d).', $reference, $seen[$folded], $index));
            }

            $seen[$folded] = $index;
            $normalized[] = $reference;
        }

        return $normalized;
    }
}
