<?php

declare(strict_types=1);

namespace Gcego\Panels;

use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Property;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Navigation\Navigation;
use Gcego\Pages\Page;
use Gcego\Panels\Branding\Branding;
use Gcego\Panels\Contracts\PanelAccessPolicy;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Definitions\PageReference;
use Gcego\Panels\Menu\UserMenuItem;
use Gcego\Registry\Contracts\DerivesDefinitions;
use Gcego\Security\Contracts\Requirement;

/**
 * An administrative workspace: a route prefix, route names, middleware,
 * an authentication guard, access rules, tenancy, the modules and pages it
 * includes, a dashboard, a navigation and a responsive shell.
 *
 *     return new Panel(
 *         new Property(Panel::Name, 'admin'),
 *         new Property(Panel::Title, 'GCEGO Administration'),
 *         new Property(Panel::Path, 'admin'),
 *         new Permission('panels.admin'),               // who may enter (or Panel::AccessPolicy)
 *
 *         new ModuleReference('academics'),
 *         new PageReference('overview'),
 *         new Property(Panel::Dashboard, 'overview'),
 *         new Branding(new Property(Branding::Name, 'GCEGO'), new Property(Branding::Logo, '/logo.svg')),
 *     );
 *
 * A gcego/registry definition of kind "panel" ("panel:admin"), discovered
 * from app/GCEGo/Panels. Panels are deny-by-default: without a
 * requirement or an AccessPolicy nobody may enter, and access to one
 * panel never implies another. Construction never queries, registers
 * routes or authenticates.
 *
 * It derives its navigation ("navigation:panel-admin") unless it names one.
 */
class Panel extends Component implements DerivesDefinitions
{
    public const string Title = 'title';

    /** URL prefix ("admin", "school/manage"); "" for the root. Default: the name. */
    public const string Path = 'path';

    /** Route domain ("admin.example.com", "{institution}.example.com"); default: any host. */
    public const string Domain = 'domain';

    /** Route name segment after gcego-panels.routing.name_prefix (default: the name). */
    public const string RouteName = 'route_name';

    /** Authentication guard (default: gcego-panels.security.guard, else the application default). */
    public const string Guard = 'guard';

    /** Extra route middleware (names or aliases), after the panel's own. */
    public const string Middleware = 'middleware';

    /** A PanelAccessPolicy class, checked with the panel's requirements. */
    public const string AccessPolicy = 'access_policy';

    /** Tenancy (gcego/security): null, "optional" or "required". */
    public const string Tenant = 'tenant';

    /** Require a verified email address (Laravel's "verified" middleware). */
    public const string Verified = 'verified';

    /** The page shown at the panel's home (a page name; added to the panel). */
    public const string Dashboard = 'dashboard';

    /** A registered gcego/navigation navigation to use instead of the derived one. */
    public const string Navigation = 'navigation';

    /** Shell layout: sidebar, topbar or compact. */
    public const string Layout = 'layout';

    /** A gcego/chroma theme name. */
    public const string Theme = 'theme';

    /** Order in the panel switcher. */
    public const string Order = 'order';

    /** Leave the panel out of the panel switcher. */
    public const string Hidden = 'hidden';

    /** Vite entries of the panel's pages (default: gcego-panels.assets.vite). */
    public const string Vite = 'vite';

    /** Styling slots (with Tailwind::Wrapper, Content and Footer). */
    public const string SidebarSlot = 'sidebar';

    public const string SidebarHeaderSlot = 'sidebar_header';

    public const string SidebarBodySlot = 'sidebar_body';

    public const string SidebarFooterSlot = 'sidebar_footer';

    public const string TopbarSlot = 'topbar';

    public const string MainSlot = 'main';

    public const string DrawerSlot = 'drawer';

    public const string BrandingSlot = 'branding';

    public const string UserMenuSlot = 'user_menu';

    public const string ProfileSlot = 'profile';

    public const string SwitcherSlot = 'switcher';

    public const string NamePattern = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    public const string PathPattern = '/^(?:[a-z0-9]+(?:[-_][a-z0-9]+)*(?:\/[a-z0-9]+(?:[-_][a-z0-9]+)*)*)?$/';

    public const string DomainPattern = '/^(?:\{[a-z][a-z0-9_]{0,31}\}|[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/';

    public const array Layouts = ['sidebar', 'topbar', 'compact'];

    /**
     * @return array<string, string> Slot => description.
     */
    public static function styleSlots(): array
    {
        return [
            Tailwind::Wrapper => 'The whole shell.',
            self::SidebarSlot => 'The desktop sidebar.',
            self::SidebarHeaderSlot => 'The sidebar header (branding).',
            self::SidebarBodySlot => 'The sidebar navigation area.',
            self::SidebarFooterSlot => 'The sidebar footer.',
            self::TopbarSlot => 'The top bar.',
            self::MainSlot => 'The column holding the top bar and the content.',
            Tailwind::Content => 'The page content area.',
            Tailwind::Footer => 'The footer.',
            self::DrawerSlot => 'The mobile navigation drawer.',
            self::BrandingSlot => 'The logo and name.',
            self::UserMenuSlot => 'The user menu.',
            self::ProfileSlot => 'The signed-in user at the foot of the sidebar.',
            self::SwitcherSlot => 'The panel switcher.',
        ];
    }

    public function panelName(): string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : '';
    }

    public function title(): string
    {
        $title = $this->get(self::Title);

        return is_string($title) && $title !== '' ? $title : ucwords(str_replace('-', ' ', $this->panelName()));
    }

    /**
     * The URL prefix, without leading or trailing "/".
     */
    public function path(): string
    {
        $path = $this->get(self::Path);

        return is_string($path) ? trim($path, '/') : $this->panelName();
    }

    public function domain(): ?string
    {
        return $this->stringProperty(self::Domain);
    }

    public function routeName(): string
    {
        return $this->stringProperty(self::RouteName) ?? $this->panelName();
    }

    public function guard(): ?string
    {
        return $this->stringProperty(self::Guard);
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
     * @return class-string<PanelAccessPolicy>|null
     */
    public function accessPolicy(): ?string
    {
        $policy = $this->get(self::AccessPolicy);

        return is_string($policy) && is_a($policy, PanelAccessPolicy::class, true) ? $policy : null;
    }

    public function tenant(): ?string
    {
        return $this->stringProperty(self::Tenant);
    }

    public function requiresVerifiedEmail(): bool
    {
        return $this->get(self::Verified) === true;
    }

    public function dashboard(): ?string
    {
        return $this->stringProperty(self::Dashboard);
    }

    /**
     * The navigation name ("panel-admin" unless Panel::Navigation names another).
     */
    public function navigationName(): string
    {
        return $this->stringProperty(self::Navigation) ?? 'panel-'.$this->panelName();
    }

    public function layout(): ?string
    {
        return $this->stringProperty(self::Layout);
    }

    public function theme(): ?string
    {
        return $this->stringProperty(self::Theme);
    }

    public function order(): int
    {
        $order = $this->get(self::Order);

        return is_int($order) ? $order : 0;
    }

    public function isHidden(): bool
    {
        return $this->get(self::Hidden) === true;
    }

    /**
     * @return list<string>|null
     */
    public function vite(): ?array
    {
        $vite = $this->get(self::Vite);

        return is_array($vite) ? array_values(array_filter($vite, is_string(...))) : null;
    }

    /**
     * Module names, in declaration order.
     *
     * @return list<string>
     */
    public function modules(): array
    {
        return array_map(static fn (ModuleReference $reference): string => $reference->module(), $this->childrenOf(ModuleReference::class));
    }

    /**
     * Page names, in declaration order (the dashboard first when it is not referenced).
     *
     * @return list<string>
     */
    public function pages(): array
    {
        $pages = array_map(static fn (PageReference $reference): string => $reference->page(), $this->childrenOf(PageReference::class));
        $dashboard = $this->dashboard();

        return $dashboard === null || in_array($dashboard, $pages, true) ? $pages : [$dashboard, ...$pages];
    }

    /**
     * Referenced pages left out of the panel's navigation.
     *
     * @return list<string>
     */
    public function unlistedPages(): array
    {
        return array_values(array_map(static fn (PageReference $reference): string => $reference->page(), array_filter($this->childrenOf(PageReference::class), static fn (PageReference $reference): bool => ! $reference->listed())));
    }

    public function branding(): ?Branding
    {
        return $this->childrenOf(Branding::class)[0] ?? null;
    }

    /**
     * @return list<UserMenuItem>
     */
    public function userMenu(): array
    {
        return $this->childrenOf(UserMenuItem::class);
    }

    /**
     * @return list<Requirement>
     */
    public function requirements(): array
    {
        return array_values(array_filter($this->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));
    }

    /**
     * Whether the panel declares who may enter (requirements or an access policy).
     */
    public function declaresAccess(): bool
    {
        return $this->requirements() !== [] || $this->accessPolicy() !== null;
    }

    /**
     * The panel's own navigation, unless it names a registered one.
     */
    public function derivedDefinitions(): iterable
    {
        if ($this->stringProperty(self::Navigation) !== null) {
            return [];
        }

        return [new Navigation(new Property(Navigation::Name, $this->navigationName()), new Property(Navigation::Label, $this->title()))];
    }

    /**
     * Definition problems beyond property types.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        if (preg_match(self::NamePattern, $this->panelName()) !== 1 || strlen($this->panelName()) > 50) {
            return ['a panel needs a Name of lowercase words separated by "-", such as "admin".'];
        }

        $problems = [];

        if (preg_match(self::PathPattern, $this->path()) !== 1 || strlen($this->path()) > 100) {
            $problems[] = sprintf('the Path "%s" must be lowercase URL segments such as "admin" or "school/manage" (no parameters).', mb_substr($this->path(), 0, 60));
        }

        foreach (['module' => $this->modules(), 'page' => $this->pages()] as $kind => $names) {
            foreach (array_count_values($names) as $name => $count) {
                if ($count > 1) {
                    $problems[] = sprintf('the %s "%s" is referenced twice.', $kind, $name);
                }
            }
        }

        if (count($this->childrenOf(Branding::class)) > 1) {
            $problems[] = 'declare at most one Branding.';
        }

        $policy = $this->get(self::AccessPolicy);

        if ($policy !== null && $this->accessPolicy() === null) {
            $problems[] = sprintf('the AccessPolicy must be a class implementing %s.', PanelAccessPolicy::class);
        }

        return $problems;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $text = static fn (int $max): \Closure => static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= $max;

        return parent::defineSchema()
            ->withType('panels.panel')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && strlen($value) <= 50 && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'lowercase words separated by "-", such as "admin"'),
                new PropertyDefinition(self::Title, Type::string(), rule: $text(120), ruleDescription: 'a title of at most 120 characters'),
                new PropertyDefinition(self::Description, Type::string(), rule: $text(300), ruleDescription: 'a text of at most 300 characters'),
                new PropertyDefinition(self::Path, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && strlen($value) <= 102, ruleDescription: 'a URL path'),
                new PropertyDefinition(self::Domain, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && strlen($value) <= 253 && preg_match(self::DomainPattern, $value) === 1, ruleDescription: 'a host name such as "admin.example.com" or "{institution}.example.com"'),
                new PropertyDefinition(self::RouteName, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z0-9_-]+(?:\.[a-z0-9_-]+)*$/', $value) === 1 && strlen($value) <= 60, ruleDescription: 'a dotted route name segment such as "admin"'),
                new PropertyDefinition(self::Guard, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $value) === 1, ruleDescription: 'a guard name'),
                new PropertyDefinition(self::Middleware, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $entry): bool => ! is_string($entry) || preg_match('/^[A-Za-z0-9_.:,\\\\-]{1,190}$/', $entry) !== 1) === [], ruleDescription: 'a list of middleware names or aliases'),
                new PropertyDefinition(self::AccessPolicy, Type::string()),
                new PropertyDefinition(self::Tenant, Type::string(), allowedValues: ['optional', 'required']),
                new PropertyDefinition(self::Verified, Type::bool(), default: false),
                new PropertyDefinition(self::Dashboard, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Page::NamePattern, $value) === 1, ruleDescription: 'a page name'),
                new PropertyDefinition(self::Navigation, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $value) === 1, ruleDescription: 'a navigation name'),
                new PropertyDefinition(self::Layout, Type::string(), allowedValues: self::Layouts),
                new PropertyDefinition(self::Theme, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $value) === 1, ruleDescription: 'a theme name'),
                new PropertyDefinition(self::Order, Type::int()),
                new PropertyDefinition(self::Hidden, Type::bool(), default: false),
                new PropertyDefinition(self::Vite, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $entry): bool => ! is_string($entry) || preg_match('/^[A-Za-z0-9_.\/-]{1,190}$/', $entry) !== 1 || str_contains($entry, '..')) === [], ruleDescription: 'a list of Vite entry paths'),
            )
            ->withAllowedChildren([ModuleReference::class, PageReference::class, Branding::class, UserMenuItem::class])
            ->withConstraint(static fn (ComponentInterface $panel): array => $panel instanceof self ? $panel->problems() : []);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Title];
    }

    private function stringProperty(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @template T of ComponentInterface
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    private function childrenOf(string $class): array
    {
        $matches = [];

        foreach ($this->children() as $child) {
            if ($child instanceof $class) {
                $matches[] = $child;
            }
        }

        return $matches;
    }
}
