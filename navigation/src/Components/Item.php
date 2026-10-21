<?php

declare(strict_types=1);

namespace Gcego\Navigation\Components;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Navigation\Contracts\BreadcrumbProvider;
use Gcego\Navigation\Contracts\NavigationBadgeProvider;
use Gcego\Navigation\NavigationNode;
use Gcego\Tables\Support\SafeUrl;
use Illuminate\Database\Eloquent\Model;

/**
 * A link to a page of the application.
 *
 *     new Item('Students', '/admin/students')                    // a path
 *     new Item('Students', 'admin.students.index')               // a named route (preferred)
 *     new Item(new Property(Item::Name, 'subjects'), new Property(Item::Label, 'Subjects'),
 *         new Property(Item::Route, 'admin.subjects.index'), new Property(Item::Active, 'admin.subjects.*'),
 *         new Property(Item::Icon, 'academic-cap'), new Property(Item::Badge, PendingRequestsBadge::class),
 *         new Property(Item::Permission, 'subjects.view'))
 *
 * The second shorthand value is a path or URL when it starts with "/" or a
 * scheme, otherwise a route name. Route parameters are scalars, or ":tenant"
 * for the current tenant's key. An item whose route does not exist, cannot
 * be generated or needs a tenant there is none of is not shown.
 */
final class Item extends NavigationNode
{
    /** A route name or path (shorthand). Route and Url win over it. */
    public const string Link = 'link';

    public const string Route = 'route';

    /** Route parameters: scalars, or ":tenant". */
    public const string Parameters = 'parameters';

    /** A root-relative path or an http(s) URL. */
    public const string Url = 'url';

    /** Route-name patterns ("admin.subjects.*") or path patterns ("/admin/subjects/*") that also make the item active. */
    public const string Active = 'active';

    /** Also require the item's query parameters to be in the current URL (default: gcego-navigation.active.match_query_string). */
    public const string MatchQuery = 'match_query';

    /** A static value (12, "New") or a NavigationBadgeProvider class. */
    public const string Badge = 'badge';

    /** Variant of a static badge: default, primary, success, warning, danger, info. */
    public const string BadgeVariant = 'badge_variant';

    /** Accessible meaning of a static badge ("unread messages"). */
    public const string BadgeLabel = 'badge_label';

    /**
     * Show how many records of this model the user may see as the badge (one aggregate query;
     * policies and tenant scopes apply). Zero shows nothing. Variant: BadgeVariant, else "default".
     */
    public const string BadgeCount = 'badge_count';

    /** Cache a provider or count badge this many seconds, per user and tenant (default: not cached). */
    public const string BadgeTtl = 'badge_ttl';

    /** A BreadcrumbProvider for pages below this item. */
    public const string Breadcrumbs = 'breadcrumbs';

    public const string Tooltip = 'tooltip';

    public const string RoutePattern = '/^[A-Za-z0-9_.:-]{1,190}$/';

    public const string ActivePattern = '/^[A-Za-z0-9_.:\/*{}-]{1,190}$/';

    public function nodeType(): string
    {
        return 'item';
    }

    public function routeName(): ?string
    {
        $route = $this->string(self::Route);

        if ($route !== null) {
            return $route;
        }

        $link = $this->string(self::Link);

        return $link !== null && $this->string(self::Url) === null && ! self::looksLikeUrl($link) ? $link : null;
    }

    public function url(): ?string
    {
        if ($this->string(self::Route) !== null) {
            return null;
        }

        $url = $this->string(self::Url) ?? $this->string(self::Link);

        return $url !== null && self::looksLikeUrl($url) ? SafeUrl::link($url) : null;
    }

    /**
     * @return array<string, scalar>
     */
    public function parameters(): array
    {
        $parameters = $this->get(self::Parameters);

        return is_array($parameters) ? array_filter($parameters, static fn (mixed $value, int|string $key): bool => is_string($key) && is_scalar($value), ARRAY_FILTER_USE_BOTH) : [];
    }

    /**
     * @return list<string>
     */
    public function activePatterns(): array
    {
        $active = $this->get(self::Active);

        return array_values(array_filter(is_array($active) ? $active : [$active], is_string(...)));
    }

    public function matchesQuery(): ?bool
    {
        $match = $this->get(self::MatchQuery);

        return is_bool($match) ? $match : null;
    }

    public function badge(): int|string|null
    {
        $badge = $this->get(self::Badge);

        return is_int($badge) || is_string($badge) ? $badge : null;
    }

    /**
     * @return class-string<NavigationBadgeProvider>|null
     */
    public function badgeProvider(): ?string
    {
        $badge = $this->get(self::Badge);

        return is_string($badge) && is_subclass_of($badge, NavigationBadgeProvider::class) ? $badge : null;
    }

    /**
     * @return class-string<Model>|null
     */
    public function badgeCount(): ?string
    {
        $model = $this->get(self::BadgeCount);

        return is_string($model) && is_subclass_of($model, Model::class) ? $model : null;
    }

    /**
     * @return class-string<BreadcrumbProvider>|null
     */
    public function breadcrumbProvider(): ?string
    {
        $provider = $this->get(self::Breadcrumbs);

        return is_string($provider) && is_subclass_of($provider, BreadcrumbProvider::class) ? $provider : null;
    }

    public function tooltip(): ?string
    {
        return $this->string(self::Tooltip);
    }

    public function description(): ?string
    {
        return $this->string(self::Description);
    }

    public static function looksLikeUrl(string $value): bool
    {
        return str_starts_with($value, '/') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $value) === 1;
    }

    protected static function shorthand(): array
    {
        return [self::Label, self::Link];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $text = static fn (int $max): \Closure => static fn (mixed $value): bool => is_string($value) && $value !== '' && mb_strlen($value) <= $max;

        return parent::defineSchema()
            ->withProperties(
                new PropertyDefinition(self::Label, Type::string(), required: true, rule: $text(120), ruleDescription: 'a label of at most 120 characters'),
                new PropertyDefinition(self::Link, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && (self::looksLikeUrl($value) ? SafeUrl::link($value) !== null : preg_match(self::RoutePattern, $value) === 1), ruleDescription: 'a route name, a root-relative path or an http(s) URL'),
                new PropertyDefinition(self::Route, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::RoutePattern, $value) === 1, ruleDescription: 'a route name such as "admin.students.index"'),
                new PropertyDefinition(self::Parameters, Type::array(), rule: static function (mixed $value): bool {
                    if (! is_array($value)) {
                        return false;
                    }

                    foreach ($value as $key => $parameter) {
                        if (! is_string($key) || ! (is_int($parameter) || is_string($parameter) || is_bool($parameter) || is_float($parameter))) {
                            return false;
                        }
                    }

                    return true;
                }, ruleDescription: 'route parameters keyed by name, with scalar values or ":tenant"'),
                new PropertyDefinition(self::Url, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && self::looksLikeUrl($value) && SafeUrl::link($value) !== null, ruleDescription: 'a root-relative path, an http(s) or a mailto URL'),
                new PropertyDefinition(self::Active, [Type::string(), Type::array()], rule: static function (mixed $value): bool {
                    foreach (is_array($value) ? $value : [$value] as $pattern) {
                        if (! is_string($pattern) || preg_match(self::ActivePattern, $pattern) !== 1) {
                            return false;
                        }
                    }

                    return true;
                }, ruleDescription: 'route-name or path patterns such as "admin.subjects.*" or "/admin/subjects/*"'),
                new PropertyDefinition(self::MatchQuery, Type::bool()),
                new PropertyDefinition(self::Badge, [Type::int(), Type::string()], rule: static fn (mixed $value): bool => is_int($value) || (is_string($value) && (str_contains($value, '\\') ? is_subclass_of($value, NavigationBadgeProvider::class) : mb_strlen($value) <= 12 && $value !== '')), ruleDescription: 'a number, a short text or a NavigationBadgeProvider class'),
                self::choice(self::BadgeVariant, ['default', 'primary', 'success', 'warning', 'danger', 'info']),
                new PropertyDefinition(self::BadgeLabel, Type::string(), rule: $text(80), ruleDescription: 'a short text'),
                new PropertyDefinition(self::BadgeCount, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && is_subclass_of($value, Model::class), ruleDescription: 'an Eloquent model class'),
                new PropertyDefinition(self::BadgeTtl, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 86400, ruleDescription: '1 to 86400 seconds'),
                new PropertyDefinition(self::Breadcrumbs, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && is_subclass_of($value, BreadcrumbProvider::class), ruleDescription: 'a BreadcrumbProvider class'),
                new PropertyDefinition(self::Tooltip, Type::string(), rule: $text(160), ruleDescription: 'a short text'),
                self::iconProperty(),
            )
            ->withoutChildren()
            ->withConstraint(static fn (ComponentInterface $item): ?string => $item instanceof self && $item->routeName() === null && $item->url() === null ? 'an item needs a route or a URL.' : null);
    }

    /**
     * @param  list<string>  $values
     */
    private static function choice(string $key, array $values): PropertyDefinition
    {
        return new PropertyDefinition($key, Type::string(), allowedValues: $values);
    }
}
