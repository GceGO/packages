<?php

declare(strict_types=1);

namespace Gcego\Navigation;

use Gcego\Navigation\Contracts\NavigationContributor;
use Gcego\Navigation\Exceptions\InvalidNavigationException;

/**
 * Validated gcego-navigation configuration.
 */
final readonly class NavigationConfig
{
    /**
     * @param  list<class-string<NavigationContributor>>  $contributors
     */
    public function __construct(
        public string $defaultVisibility = 'authenticated',
        public bool $compactToggle = true,
        public bool $defaultCompact = false,
        public bool $collapsibleGroups = true,
        public int $maxDepth = 3,
        public bool $matchQueryString = false,
        public bool $breadcrumbs = true,
        public bool $absoluteUrls = false,
        public bool $wireNavigate = false,
        public ?string $badgeStore = null,
        public array $contributors = [],
        public int $maxNodes = 200,
        public int $maxBadges = 10,
        public bool $search = true,
    ) {
        if (! in_array($defaultVisibility, ['authenticated', 'public', 'deny'], true)) {
            throw InvalidNavigationException::invalid('gcego-navigation.visibility.default', 'use "authenticated", "public" or "deny".');
        }

        if ($maxDepth < 1 || $maxDepth > 6) {
            throw InvalidNavigationException::invalid('gcego-navigation.groups.max_depth', 'use 1 to 6.');
        }

        foreach ($contributors as $contributor) {
            if (! is_subclass_of($contributor, NavigationContributor::class)) {
                throw InvalidNavigationException::invalid('gcego-navigation.contributors', sprintf('%s is not a %s.', $contributor, NavigationContributor::class));
            }
        }

        if ($maxNodes < 1 || $maxBadges < 0) {
            throw InvalidNavigationException::invalid('gcego-navigation.diagnostics', 'limits must be positive.');
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $read = static function (string $section, string $key, mixed $default) use ($config): mixed {
            $values = $config[$section] ?? [];

            return is_array($values) && array_key_exists($key, $values) ? $values[$key] : $default;
        };
        $bool = static fn (string $section, string $key, bool $default): bool => is_bool($value = $read($section, $key, $default)) ? $value : throw InvalidNavigationException::invalid("gcego-navigation.$section.$key", 'must be true or false.');
        $int = static fn (string $section, string $key, int $default): int => is_int($value = $read($section, $key, $default)) ? $value : throw InvalidNavigationException::invalid("gcego-navigation.$section.$key", 'must be a whole number.');
        $visibility = $read('visibility', 'default', 'authenticated');
        $store = $read('badges', 'cache_store', null);
        $contributors = $config['contributors'] ?? [];

        if (! is_array($contributors) || array_filter($contributors, static fn (mixed $class): bool => ! is_string($class)) !== []) {
            throw InvalidNavigationException::invalid('gcego-navigation.contributors', 'must be a list of class names.');
        }

        /** @var list<class-string<NavigationContributor>> $contributors */
        $contributors = array_values($contributors);

        return new self(
            is_string($visibility) ? $visibility : 'invalid',
            $bool('sidebar', 'compact_toggle', true),
            $bool('sidebar', 'default_compact', false),
            $bool('groups', 'collapsible', true),
            $int('groups', 'max_depth', 3),
            $bool('active', 'match_query_string', false),
            $bool('breadcrumbs', 'enabled', true),
            $bool('links', 'absolute', false),
            $bool('links', 'wire_navigate', false),
            is_string($store) ? $store : null,
            $contributors,
            $int('diagnostics', 'max_nodes', 200),
            $int('diagnostics', 'max_badges', 10),
            $bool('sidebar', 'search', true),
        );
    }
}
