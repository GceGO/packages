<?php

declare(strict_types=1);

namespace Gcego\Chroma\Classes;

/**
 * Merges an override class list onto a base list with explicit, deterministic rules.
 *
 * For each override class, in order:
 *  - an exact duplicate in the base is kept as is;
 *  - otherwise every base class with the same variant stack (order-insensitive:
 *    `md:hover:` equals `hover:md:`) and the same important flag whose group is
 *    the override's group, or one the override refines, is removed; the
 *    override takes the position of the first removed class;
 *  - otherwise the override is appended.
 *
 * Classes without a known group (see UtilityGroups) never conflict.
 */
final class ClassMerger
{
    /**
     * @var array<string, array{0: string, 1: list<string>}|false>
     */
    private array $parsed = [];

    public function __construct(private readonly UtilityGroups $groups = new UtilityGroups) {}

    /**
     * @param  list<string>  $base
     * @param  list<string>  $overrides
     * @return list<string>
     */
    public function merge(array $base, array $overrides): array
    {
        $result = $base;

        foreach ($overrides as $override) {
            if (in_array($override, $result, true)) {
                continue;
            }

            $key = $this->key($override);
            $position = null;

            if ($key !== false) {
                [$scope, $overridden] = $key;

                foreach ($result as $index => $existing) {
                    $existingKey = $this->key($existing);

                    if ($existingKey === false || ! in_array($existingKey[0], $overridden, true)) {
                        continue;
                    }

                    $position ??= $index;
                    unset($result[$index]);
                }

                $result = $this->insert($result, $position, $override);

                continue;
            }

            $result[] = $override;
        }

        return array_values($result);
    }

    /**
     * Whether two classes would conflict (for diagnostics and tests).
     */
    public function conflicts(string $a, string $b): bool
    {
        $keyA = $this->key($a);
        $keyB = $this->key($b);

        return $keyA !== false && $keyB !== false && (in_array($keyB[0], $keyA[1], true) || in_array($keyA[0], $keyB[1], true));
    }

    /**
     * @param  array<int, string>  $result
     * @return array<int, string>
     */
    private function insert(array $result, ?int $position, string $class): array
    {
        if ($position === null) {
            $result[] = $class;

            return $result;
        }

        $result[$position] = $class;
        ksort($result);

        return $result;
    }

    /**
     * [scoped group, scoped groups this class overrides] or false when the class has no group.
     *
     * @return array{0: string, 1: list<string>}|false
     */
    private function key(string $class): array|false
    {
        if (isset($this->parsed[$class])) {
            return $this->parsed[$class];
        }

        $segments = $this->segments($class);
        $utility = (string) array_pop($segments);
        $important = str_starts_with($utility, '!') || str_ends_with($utility, '!');
        $utility = trim($utility, '!');
        sort($segments);
        $scope = implode(':', $segments).($important ? '!' : '').'|';
        $group = $this->groups->classify($utility);

        if ($group === null) {
            return $this->parsed[$class] = false;
        }

        return $this->parsed[$class] = [
            $scope.$group[0],
            array_map(static fn (string $refined): string => $scope.$refined, [$group[0], ...$group[1]]),
        ];
    }

    /**
     * Split on ":" outside [] and ().
     *
     * @return list<string>
     */
    private function segments(string $class): array
    {
        $segments = [];
        $current = '';
        $depth = 0;

        foreach (str_split($class) as $character) {
            if ($character === '[' || $character === '(') {
                $depth++;
            } elseif ($character === ']' || $character === ')') {
                $depth--;
            }

            if ($character === ':' && $depth === 0) {
                $segments[] = $current;
                $current = '';

                continue;
            }

            $current .= $character;
        }

        $segments[] = $current;

        return $segments;
    }
}
