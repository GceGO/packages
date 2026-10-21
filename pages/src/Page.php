<?php

declare(strict_types=1);

namespace Gcego\Pages;

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Data\Source;
use Gcego\Pages\Contracts\PageComponent;
use Gcego\Pages\Contracts\PageTitleProvider;
use Gcego\Pages\Presets\PagePresets;
use Gcego\Pages\Records\RecordKey;
use Gcego\Tables\Support\Icons;
use Gcego\Widgets\Widget;
use Illuminate\Support\Str;

/**
 * A declarative administration page: metadata plus GCEGO components.
 *
 *     new Page(
 *         new Property(Page::Name, 'about'),
 *         new Property(Page::Title, 'About GCEGO'),
 *         new Authenticated,
 *         new Section('About this platform', new Content('GCEGO helps candidates prepare for the GCE.')),
 *     )
 *
 *     new Page('about', 'About GCEGO', new Authenticated, …)          // shorthand: Name, Title
 *
 * A Page is a gcego/blueprints Blueprint registered under the "page" kind
 * ("page:about"), so the tables, forms, infolists, actions, layouts and
 * widgets it contains are trusted blueprint parts: their own Livewire hosts
 * find them through the page's identity, exactly as for a blueprint.
 *
 * Names may be dotted ("subjects.create"): the registry identity replaces the
 * dots with "-" ("page:subjects-create"); the route name keeps them
 * ("gcego.subjects.create"). Construction never queries, renders, mounts
 * Livewire or resolves routes.
 *
 * Access is deny-by-default (gcego-pages.authorization.default): add a
 * gcego/security requirement (Permission, Ability, Authenticated, TenantMember,
 * PublicAccess, …) to the page.
 */
class Page extends Blueprint
{
    /** The URL path below the routing prefix ("subjects/{record}/edit"); derived from the name when omitted. */
    public const string Path = 'path';

    /** The route name below the name prefix; the page name when omitted. */
    public const string Route = 'route';

    /** Extra route middleware (names or aliases), after gcego-pages.routing.middleware. */
    public const string Middleware = 'middleware';

    /** Route constraints of extra path parameters: ['institution' => '[0-9]+']. */
    public const string Where = 'where';

    /** Custom pages only: the page shows one record ({record} in the path). */
    public const string Record = 'record';

    /** The shape of {record}: id, uuid, ulid, slug or key (gcego-pages.pages.record_key). */
    public const string RecordKey = 'record_key';

    /** The record attribute used in titles and breadcrumbs (default: name, title or label). */
    public const string RecordTitle = 'record_title';

    /** The ability authorized on the record (view pages: "view", edit pages: "update"). */
    public const string RecordAbility = 'record_ability';

    /** A PageTitleProvider class for trusted dynamic titles and descriptions. */
    public const string TitleProvider = 'title_provider';

    /** The parent page name in breadcrumbs (default: the name without its last segment, when that page exists). */
    public const string Parent = 'parent';

    /** Show breadcrumbs (default: true). */
    public const string Breadcrumbs = 'breadcrumbs';

    /** Content width: narrow, standard, wide or full (gcego-pages.pages.default_width). */
    public const string Width = 'width';

    /** Text shown when the page has nothing the user may see. */
    public const string Empty = 'empty';

    /** Where Create and Edit pages go after saving: view, edit, index, self, none or a page name. */
    public const string RedirectTo = 'redirect_to';

    /** Opt in to gcego/navigation (PageNavigationContributor). Pages are never added on their own. */
    public const string Navigation = 'navigation';

    public const string NavigationLabel = 'navigation_label';

    public const string NavigationGroup = 'navigation_group';

    public const string NavigationOrder = 'navigation_order';

    public const string NavigationIcon = 'navigation_icon';

    /** Styling slots of pages (with Tailwind::Wrapper, Header, Title, Description, Actions, Body and Icon). */
    public const string BreadcrumbsSlot = 'breadcrumbs';

    public const string EmptySlot = 'empty';

    public const string LoadingSlot = 'loading';

    public const string NamePattern = '/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*(?:\.[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*){0,4}$/';

    public const string ParameterPattern = '/^[a-z][a-z0-9_]{0,31}$/';

    public const string LiteralSegmentPattern = '/^[a-z0-9]+(?:[-_.][a-z0-9]+)*$/';

    public const string RecordParameter = 'record';

    /** Record pages whose last name segment is one of these show the record at {record} itself. */
    private const array ViewSegments = ['view', 'show'];

    /**
     * The kind of page: index, create, edit, view, dashboard or custom.
     */
    public static function pageType(): string
    {
        return 'custom';
    }

    /**
     * The blueprint role this type of page coordinates (and requires), if any.
     */
    public static function requiredRole(): ?string
    {
        return null;
    }

    /**
     * @return array<string, string> Slot => description.
     */
    public static function styleSlots(): array
    {
        return [
            Tailwind::Wrapper => 'The page.',
            Tailwind::Header => 'The page header.',
            Tailwind::Title => 'The page title (h1).',
            Tailwind::Description => 'The description under the title.',
            Tailwind::Icon => 'The title icon.',
            self::BreadcrumbsSlot => 'The breadcrumb trail.',
            Tailwind::Actions => 'The header actions.',
            Tailwind::Body => 'The page content.',
            self::EmptySlot => 'The empty state.',
            self::LoadingSlot => 'The loading indicator.',
        ];
    }

    public function pageName(): string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : '';
    }

    /**
     * The name in the registry ("subjects.create" -> "subjects-create").
     */
    public function registryName(): string
    {
        return str_replace('.', '-', $this->pageName());
    }

    /**
     * The static title: Title, else the name's last segment as words.
     */
    public function pageTitle(): string
    {
        $title = $this->get(self::Title);

        if (is_string($title)) {
            return $title;
        }

        $segments = explode('.', $this->pageName());

        return Str::headline((string) end($segments));
    }

    public function pageDescription(): ?string
    {
        return $this->stringProperty(self::Description);
    }

    public function icon(): ?string
    {
        return $this->stringProperty(self::Icon);
    }

    /**
     * Whether the page shows one record ({record} in its path).
     */
    public function requiresRecord(): bool
    {
        return static::recordPage() ?? $this->get(self::Record) === true;
    }

    public function recordAbility(): string
    {
        return $this->stringProperty(self::RecordAbility) ?? static::defaultRecordAbility();
    }

    public function recordKey(string $default = 'key'): string
    {
        return $this->stringProperty(self::RecordKey) ?? $default;
    }

    public function recordTitle(): ?string
    {
        return $this->stringProperty(self::RecordTitle);
    }

    /**
     * @return class-string<PageTitleProvider>|null
     */
    public function titleProvider(): ?string
    {
        $provider = $this->get(self::TitleProvider);

        return is_string($provider) && is_subclass_of($provider, PageTitleProvider::class) ? $provider : null;
    }

    /**
     * The URL path below the routing prefix, without leading or trailing "/".
     */
    public function path(): string
    {
        $path = $this->get(self::Path);

        return is_string($path) ? trim($path, '/') : $this->defaultPath();
    }

    /**
     * The route name below gcego-pages.routing.name_prefix.
     */
    public function routeName(): string
    {
        return $this->stringProperty(self::Route) ?? $this->pageName();
    }

    /**
     * @return list<string>
     */
    public function middleware(): array
    {
        $middleware = $this->get(self::Middleware);

        return is_array($middleware) ? array_values(array_filter($middleware, is_string(...))) : [];
    }

    /**
     * @return array<string, string>
     */
    public function where(): array
    {
        $where = $this->get(self::Where);
        $constraints = [];

        foreach (is_array($where) ? $where : [] as $parameter => $pattern) {
            if (is_string($parameter) && is_string($pattern)) {
                $constraints[$parameter] = $pattern;
            }
        }

        return $constraints;
    }

    /**
     * The path's parameters, in order ("record" included).
     *
     * @return list<string>
     */
    public function parameters(): array
    {
        preg_match_all('/\{([^}\/]*)\}/', $this->path(), $matches);

        return $matches[1];
    }

    public function parentName(): ?string
    {
        return $this->stringProperty(self::Parent);
    }

    public function showsBreadcrumbs(): bool
    {
        return $this->get(self::Breadcrumbs) !== false;
    }

    public function width(): ?string
    {
        return $this->stringProperty(self::Width);
    }

    public function emptyText(): ?string
    {
        return $this->stringProperty(self::Empty);
    }

    public function redirectTo(): ?string
    {
        return $this->stringProperty(self::RedirectTo);
    }

    /**
     * Navigation metadata (gcego/navigation), used only when the page opts in.
     *
     * @return array{enabled: bool, label: string, group: string|null, order: int, icon: string|null}
     */
    public function navigation(): array
    {
        $order = $this->get(self::NavigationOrder);

        return [
            'enabled' => $this->get(self::Navigation) === true,
            'label' => $this->stringProperty(self::NavigationLabel) ?? $this->pageTitle(),
            'group' => $this->stringProperty(self::NavigationGroup),
            'order' => is_int($order) ? $order : 0,
            'icon' => $this->stringProperty(self::NavigationIcon) ?? $this->icon(),
        ];
    }

    /**
     * The page's data source (a gcego/data Source part), if any.
     */
    public function source(): ?Source
    {
        $source = $this->part(BlueprintRole::Source);

        return $source instanceof Source ? $source : null;
    }

    /**
     * Definition problems beyond property types (also reported by validate()).
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        if (preg_match(self::NamePattern, $this->pageName()) !== 1 || strlen($this->pageName()) > 100) {
            return ['a page needs a Name of lowercase segments separated by ".", such as "subjects" or "subjects.create".'];
        }

        $problems = [...$problems, ...$this->pathProblems()];
        $role = static::requiredRole();

        if ($role !== null && ! $this->hasPart($role)) {
            $problems[] = sprintf('a %s page needs a %s (the %s part it coordinates).', static::pageType(), ucfirst($role), $role);
        }

        if (($this->requiresRecord() || in_array($role, [BlueprintRole::Table, BlueprintRole::Form], true)) && ! $this->hasPart(BlueprintRole::Source)) {
            $problems[] = sprintf('a %s page needs a gcego/data Source (new Source(Model::class)), so its records are looked up in scope and authorized.', static::pageType());
        }

        $parent = $this->parentName();

        if ($parent !== null && $parent === $this->pageName()) {
            $problems[] = 'a page cannot be its own Parent.';
        }

        return $problems;
    }

    /**
     * Record pages: whether the type always (true) or never (false) shows a record; null reads Page::Record.
     */
    protected static function recordPage(): ?bool
    {
        return null;
    }

    protected static function defaultRecordAbility(): string
    {
        return 'view';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $text = static fn (int $max): \Closure => static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= $max;
        $pageName = static fn (mixed $value): bool => is_string($value) && strlen($value) <= 100 && preg_match(self::NamePattern, $value) === 1;

        return parent::defineSchema()
            ->withType('pages.'.static::pageType())
            ->withoutProperties(self::Name, self::Slug)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: $pageName, ruleDescription: 'lowercase segments separated by ".", such as "subjects" or "subjects.create"', description: 'Identity, route name and default path.'),
                new PropertyDefinition(self::Path, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && strlen($value) <= 190, ruleDescription: 'a URL path of at most 190 characters'),
                new PropertyDefinition(self::Route, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z0-9_-]+(?:\.[a-z0-9_-]+)*$/', $value) === 1 && strlen($value) <= 150, ruleDescription: 'a dotted route name such as "subjects.index"'),
                new PropertyDefinition(self::Middleware, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $entry): bool => ! is_string($entry) || preg_match(PagesConfig::MiddlewarePattern, $entry) !== 1) === [], ruleDescription: 'a list of middleware names or aliases'),
                new PropertyDefinition(self::Where, Type::array(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $pattern, mixed $parameter): bool => ! is_string($parameter) || ! is_string($pattern) || ! self::validConstraint($pattern), ARRAY_FILTER_USE_BOTH) === [], ruleDescription: 'route constraints keyed by parameter name, without anchors or "/" delimiters'),
                new PropertyDefinition(self::Record, Type::bool(), default: false),
                new PropertyDefinition(self::RecordKey, Type::string(), allowedValues: array_keys(RecordKey::Patterns)),
                new PropertyDefinition(self::RecordTitle, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z_][a-z0-9_]{0,63}$/i', $value) === 1, ruleDescription: 'an attribute name such as "name"'),
                new PropertyDefinition(self::RecordAbility, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]{0,63}$/', $value) === 1, ruleDescription: 'an ability such as "view"'),
                new PropertyDefinition(self::TitleProvider, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && is_subclass_of($value, PageTitleProvider::class), ruleDescription: 'a '.PageTitleProvider::class.' class'),
                new PropertyDefinition(self::Parent, Type::string(), rule: $pageName, ruleDescription: 'a page name such as "subjects"'),
                new PropertyDefinition(self::Breadcrumbs, Type::bool(), default: true),
                new PropertyDefinition(self::Width, Type::string(), allowedValues: array_keys(PagePresets::Widths)),
                new PropertyDefinition(self::Empty, Type::string(), rule: $text(300), ruleDescription: 'a text of at most 300 characters'),
                new PropertyDefinition(self::RedirectTo, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && (in_array($value, ['view', 'edit', 'index', 'self', 'none'], true) || preg_match(self::NamePattern, $value) === 1), ruleDescription: 'view, edit, index, self, none or a page name'),
                new PropertyDefinition(self::Navigation, Type::bool(), default: false),
                new PropertyDefinition(self::NavigationLabel, Type::string(), rule: $text(120), ruleDescription: 'a label of at most 120 characters'),
                new PropertyDefinition(self::NavigationGroup, Type::string(), rule: $text(120), ruleDescription: 'a group label of at most 120 characters'),
                new PropertyDefinition(self::NavigationOrder, Type::int()),
                new PropertyDefinition(self::NavigationIcon, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Icons::NamePattern, $value) === 1, ruleDescription: 'an icon name'),
            )
            ->withAllowedChildren([BlueprintPart::class, Widget::class, PageComponent::class])
            ->withConstraint(static fn (ComponentInterface $page): array => $page instanceof self ? $page->problems() : []);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Title];
    }

    protected function stringProperty(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function defaultPath(): string
    {
        $segments = array_map(static fn (string $segment): string => str_replace('_', '-', $segment), explode('.', $this->pageName()));

        if (! $this->requiresRecord()) {
            return implode('/', $segments);
        }

        $last = (string) array_pop($segments);

        if ($segments === []) {
            return $last.'/{'.self::RecordParameter.'}';
        }

        return in_array($last, self::ViewSegments, true)
            ? implode('/', $segments).'/{'.self::RecordParameter.'}'
            : implode('/', $segments).'/{'.self::RecordParameter.'}/'.$last;
    }

    /**
     * @return list<string>
     */
    private function pathProblems(): array
    {
        $path = $this->path();
        $problems = [];
        $parameters = [];

        foreach ($path === '' ? [] : explode('/', $path) as $segment) {
            if (preg_match('/^\{(.*)\}$/', $segment, $match) === 1) {
                if (preg_match(self::ParameterPattern, $match[1]) !== 1) {
                    $problems[] = sprintf('path parameter "{%s}" must be a lowercase name such as {record} or {institution} (optional parameters are not supported).', mb_substr($match[1], 0, 40));
                } elseif (in_array($match[1], $parameters, true)) {
                    $problems[] = sprintf('path parameter "{%s}" appears twice.', $match[1]);
                }

                $parameters[] = $match[1];
            } elseif (preg_match(self::LiteralSegmentPattern, $segment) !== 1) {
                $problems[] = sprintf('path segment "%s" must use lowercase letters, digits, "-", "_" and "." (or be a {parameter}).', mb_substr($segment, 0, 40));
            }
        }

        if (count($parameters) > 8) {
            $problems[] = 'a path has at most 8 parameters.';
        }

        $hasRecord = in_array(self::RecordParameter, $parameters, true);

        if ($this->requiresRecord() && ! $hasRecord) {
            $problems[] = 'a record page needs {record} in its Path.';
        }

        if (! $this->requiresRecord() && $hasRecord) {
            $problems[] = '{record} is reserved for record pages (ViewPage, EditPage or Record true).';
        }

        foreach (array_keys($this->where()) as $parameter) {
            if ($parameter === self::RecordParameter) {
                $problems[] = 'constrain {record} with RecordKey, not Where.';
            } elseif (! in_array($parameter, $parameters, true)) {
                $problems[] = sprintf('Where constrains "%s", which is not a parameter of the path.', $parameter);
            }
        }

        return $problems;
    }

    private static function validConstraint(string $pattern): bool
    {
        if ($pattern === '' || strlen($pattern) > 200 || str_starts_with($pattern, '^') || str_ends_with($pattern, '$') || str_contains($pattern, '#')) {
            return false;
        }

        set_error_handler(static fn (): bool => true);

        try {
            return preg_match('#^(?:'.$pattern.')$#u', '') !== false;
        } finally {
            restore_error_handler();
        }
    }
}
