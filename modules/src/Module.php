<?php

declare(strict_types=1);

namespace Gcego\Modules;

use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Modules\Building\ModuleBuilder;
use Gcego\Modules\Compilation\ModuleCompiler;
use Gcego\Pages\Page;
use Gcego\Registry\Contracts\DerivesDefinitions;
use Gcego\Security\Contracts\Requirement;
use Gcego\Tables\Support\Icons;
use Illuminate\Support\Str;

/**
 * An administrative module: resources (and custom pages) that belong
 * together, with shared metadata, navigation and access requirements.
 *
 *     return new Module(
 *         new Property(Module::Name, 'academics'),
 *         new Property(Module::Title, 'Academic Management'),
 *         new Permission('academics.access'),      // required by every page of the module
 *
 *         new Resource(new Source(Subject::class), new Table(...), new Form(...), new Create, new Edit),
 *         new Resource(new Source(Topic::class), new Table(...)),
 *         new DashboardPage(new Property(DashboardPage::Name, 'overview'), ...),
 *     );
 *
 *     new Module(new Resource(Subject::class))      // one resource: the module takes its name
 *
 * A module is a gcego/registry definition of kind "module" ("module:academics")
 * that derives gcego/pages pages: each resource's index, create, view and
 * edit pages, its relationship pages and the module's custom pages. They are
 * ordinary "page" definitions, so gcego/pages routes, authorizes and renders
 * them, and gcego/tables, forms, infolists and actions do the work.
 *
 * Definitions are immutable; construction never queries, registers routes,
 * renders or mounts Livewire.
 */
class Module extends Component implements DerivesDefinitions
{
    public const string Title = 'title';

    public const string Icon = 'icon';

    /** URL prefix of the module's pages ("academics" -> /admin/academics/subjects). Default: none. */
    public const string Slug = 'slug';

    /** Add the module to the navigation (default: gcego-modules.navigation.auto). */
    public const string Navigation = 'navigation';

    /** The navigation group label (default: the title). */
    public const string NavigationGroup = 'navigation_group';

    public const string NavigationOrder = 'navigation_order';

    /** Free-form version metadata, shown in diagnostics. */
    public const string Version = 'version';

    public const string NamePattern = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    public const string SlugPattern = '/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/';

    /**
     * The module name: Name, else the name of its only resource.
     */
    public function moduleName(): string
    {
        $name = $this->get(self::Name);

        if (is_string($name) && $name !== '') {
            return $name;
        }

        $resources = $this->resources();

        return count($resources) === 1 ? $resources[0]->resourceName() : '';
    }

    public function title(): string
    {
        $title = $this->get(self::Title);

        return is_string($title) && $title !== '' ? $title : Str::headline($this->moduleName());
    }

    public function description(): ?string
    {
        return $this->stringProperty(self::Description);
    }

    public function icon(): ?string
    {
        return $this->stringProperty(self::Icon);
    }

    public function slug(): ?string
    {
        return $this->stringProperty(self::Slug);
    }

    public function version(): ?string
    {
        return $this->stringProperty(self::Version);
    }

    /**
     * Whether the module opted in or out of navigation (null: the configured default).
     */
    public function navigation(): ?bool
    {
        $navigation = $this->get(self::Navigation);

        return is_bool($navigation) ? $navigation : null;
    }

    public function navigationGroup(): string
    {
        return $this->stringProperty(self::NavigationGroup) ?? $this->title();
    }

    public function navigationOrder(): int
    {
        $order = $this->get(self::NavigationOrder);

        return is_int($order) ? $order : 0;
    }

    /**
     * @return list<\Gcego\Modules\Resource>
     */
    public function resources(): array
    {
        return $this->childrenOf(Resource::class);
    }

    public function resource(string $name): ?Resource
    {
        foreach ($this->resources() as $resource) {
            if ($resource->resourceName() === $name) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * Custom pages of the module (their names are relative to it).
     *
     * @return list<Page>
     */
    public function pages(): array
    {
        return $this->childrenOf(Page::class);
    }

    /**
     * Requirements of every page of the module.
     *
     * @return list<Requirement>
     */
    public function requirements(): array
    {
        return array_values(array_filter($this->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));
    }

    /**
     * The pages this module brings: its resources' pages, their relationship
     * pages and its custom pages (gcego/registry indexes them as "page" definitions).
     */
    public function derivedDefinitions(): iterable
    {
        return ModuleCompiler::instance()->pages($this);
    }

    /**
     * Definition problems beyond property types.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $name = $this->moduleName();

        if (preg_match(self::NamePattern, $name) !== 1 || strlen($name) > 60) {
            return ['a module needs a Name of lowercase words separated by "-" (such as "academics"); only a module with a single resource takes its name.'];
        }

        $problems = [];
        $resources = [];

        foreach ($this->resources() as $resource) {
            $resourceName = $resource->resourceName();

            if (isset($resources[$resourceName])) {
                $problems[] = sprintf('the resource "%s" is declared twice.', $resourceName);
            }

            $resources[$resourceName] = true;

            foreach ($resource->relations() as $relation) {
                $target = $relation->targetResource();

                if ($target !== null && $this->resource($target) === null) {
                    $problems[] = sprintf('the relation "%s" of "%s" names the resource "%s", which this module does not declare.', $relation->relationship(), $resourceName, $target);
                }
            }
        }

        $pages = [];

        foreach ($this->pages() as $page) {
            if (isset($pages[$page->pageName()]) || isset($resources[$page->pageName()])) {
                $problems[] = sprintf('the page "%s" is declared twice (or shares its name with a resource).', $page->pageName());
            }

            $pages[$page->pageName()] = true;
        }

        if ($this->resources() === [] && $this->pages() === []) {
            $problems[] = 'a module needs at least one Resource or page.';
        }

        return $problems;
    }

    public static function builderClass(): string
    {
        return ModuleBuilder::class;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $text = static fn (int $max): \Closure => static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= $max;

        return parent::defineSchema()
            ->withType('modules.module')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && strlen($value) <= 60 && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'lowercase words separated by "-", such as "academics"'),
                new PropertyDefinition(self::Title, Type::string(), rule: $text(120), ruleDescription: 'a title of at most 120 characters'),
                new PropertyDefinition(self::Description, Type::string(), rule: $text(500), ruleDescription: 'a text of at most 500 characters'),
                new PropertyDefinition(self::Icon, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Icons::NamePattern, $value) === 1, ruleDescription: 'an icon name'),
                new PropertyDefinition(self::Slug, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && strlen($value) <= 100 && preg_match(self::SlugPattern, $value) === 1, ruleDescription: 'lowercase URL segments such as "academics"'),
                new PropertyDefinition(self::Navigation, Type::bool()),
                new PropertyDefinition(self::NavigationGroup, Type::string(), rule: $text(120), ruleDescription: 'a label of at most 120 characters'),
                new PropertyDefinition(self::NavigationOrder, Type::int()),
                new PropertyDefinition(self::Version, Type::string(), rule: $text(40), ruleDescription: 'a version of at most 40 characters'),
            )
            ->withAllowedChildren([Resource::class, Page::class])
            ->withConstraint(static fn (ComponentInterface $module): array => $module instanceof self ? $module->problems() : []);
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
