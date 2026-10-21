<?php

declare(strict_types=1);

namespace Gcego\Modules;

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Data\Source;
use Gcego\Forms\Form;
use Gcego\Infolists\Infolist;
use Gcego\Modules\Building\ResourceBuilder;
use Gcego\Modules\Definitions\RecordSet;
use Gcego\Navigation\Presets\NavigationPresets;
use Gcego\Pages\Page;
use Gcego\Tables\Table;
use Illuminate\Support\Str;

/**
 * A manageable entity of a module: its records (a gcego/data Source or a
 * model), and the Table, Form, Infolist and actions that manage them.
 *
 *     new Resource(Subject::class)                                   // metadata only: nothing is exposed
 *
 *     new Resource(
 *         new Property(Resource::Name, 'subjects'),
 *         new Source(Subject::class),
 *         new Table(new Text('name', 'Subject Name')),               // index page
 *         new Form(new TextInput('name', 'Subject Name')),
 *         new Create, new Edit,                                       // create and edit pages
 *         new Infolist(new Entries\Text('name', 'Subject Name')),    // view page
 *         new Delete,                                                 // on the list and record pages
 *         new Relation('topics', new Property(Relation::Resource, 'topics')),   // relationship pages
 *     )
 *
 * Each supported operation becomes a gcego/pages page; nothing else is
 * implied: no columns, fields or entries are guessed, destructive actions
 * exist only when declared, and every page is authorized (the model's
 * policy by default, plus the resource's own requirements).
 *
 * Construction never queries, registers routes or mounts Livewire.
 */
class Resource extends RecordSet
{
    /** Show the resource in its module's navigation group (default: true). */
    public const string Navigation = 'navigation';

    public const string NavigationLabel = 'navigation_label';

    public const string NavigationOrder = 'navigation_order';

    /** Show how many records the user may see as the navigation item's badge (default: false). */
    public const string NavigationBadge = 'navigation_badge';

    /** The badge's color: default, primary, success, warning, danger or info (NavigationPresets::BadgeVariants). */
    public const string NavigationBadgeVariant = 'navigation_badge_variant';

    public const string NamePattern = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    /** Reserved names: they are the pages' own name segments. */
    public const array ReservedNames = ['index', 'create', 'view', 'edit', 'show'];

    /**
     * The resource name: Name, else the model's plural ("SubjectTopic" -> "subject-topics").
     */
    public function recordSetName(): string
    {
        $name = $this->get(self::Name);

        if (is_string($name) && $name !== '') {
            return $name;
        }

        $model = $this->modelClass();

        return $model === null ? '' : Str::kebab(Str::pluralStudly(class_basename($model)));
    }

    public function resourceName(): string
    {
        return $this->recordSetName();
    }

    /**
     * The model: Model, else the Source's model.
     *
     * @return class-string|null
     */
    public function modelClass(): ?string
    {
        $model = $this->model();

        if ($model !== null) {
            return $model;
        }

        $sourceModel = $this->declaredSource()?->get(Source::Model);

        return is_string($sourceModel) && class_exists($sourceModel) ? $sourceModel : null;
    }

    /**
     * The declared Source, if any.
     */
    public function declaredSource(): ?Source
    {
        return $this->childrenOf(Source::class)[0] ?? null;
    }

    /**
     * The records: the declared Source, else a Source of the model.
     */
    public function source(): ?Source
    {
        $source = $this->declaredSource();

        if ($source !== null) {
            return $source;
        }

        $model = $this->model();

        return $model === null ? null : new Source($model);
    }

    /**
     * @return list<Relation>
     */
    public function relations(): array
    {
        return $this->childrenOf(Relation::class);
    }

    /**
     * Custom pages of the resource (their names are relative to it).
     *
     * @return list<Page>
     */
    public function pages(): array
    {
        return $this->childrenOf(Page::class);
    }

    public function inNavigation(): bool
    {
        return $this->get(self::Navigation) !== false;
    }

    public function navigationLabel(): string
    {
        return $this->stringProperty(self::NavigationLabel) ?? $this->pluralLabel();
    }

    public function hasNavigationBadge(): bool
    {
        return $this->get(self::NavigationBadge) === true;
    }

    public function navigationBadgeVariant(): ?string
    {
        return $this->stringProperty(self::NavigationBadgeVariant);
    }

    public function navigationOrder(): ?int
    {
        $order = $this->get(self::NavigationOrder);

        return is_int($order) ? $order : null;
    }

    public function problems(): array
    {
        $name = $this->recordSetName();

        if (preg_match(self::NamePattern, $name) !== 1 || strlen($name) > 60) {
            return ['a resource needs a Name of lowercase words separated by "-" (such as "subjects"), or a Model to derive it from.'];
        }

        $problems = parent::problems();

        if (in_array($name, self::ReservedNames, true)) {
            $problems[] = sprintf('"%s" is reserved for page names; choose another resource name.', $name);
        }

        if (count($this->childrenOf(Source::class)) > 1) {
            $problems[] = 'declare at most one Source.';
        }

        $sourceModel = $this->declaredSource()?->get(Source::Model);

        if ($this->model() !== null && is_string($sourceModel) && $sourceModel !== $this->model()) {
            $problems[] = sprintf('the Model (%s) and the Source model (%s) differ.', $this->model(), $sourceModel);
        }

        if ($this->source() === null && ($this->table() !== null || $this->form() !== null || $this->infolist() !== null)) {
            $problems[] = 'a resource with a Table, Form or Infolist needs a Source or a Model, so its records are read in scope and authorized.';
        }

        $relations = [];

        foreach ($this->relations() as $relation) {
            $segment = $relation->recordSetName();

            if (isset($relations[$segment])) {
                $problems[] = sprintf('the relation "%s" is declared twice.', $relation->relationship());
            }

            $relations[$segment] = true;
        }

        $pages = [];

        foreach ($this->pages() as $page) {
            if (isset($pages[$page->pageName()]) || isset($relations[$page->pageName()])) {
                $problems[] = sprintf('the page "%s" is declared twice (or shares its name with a relation).', $page->pageName());
            }

            $pages[$page->pageName()] = true;
        }

        return $problems;
    }

    public static function builderClass(): string
    {
        return ResourceBuilder::class;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('modules.resource')
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && strlen($value) <= 60 && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'lowercase words separated by "-", such as "subjects"'),
                new PropertyDefinition(self::Navigation, Type::bool(), default: true),
                new PropertyDefinition(self::NavigationLabel, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 120, ruleDescription: 'a label of at most 120 characters'),
                new PropertyDefinition(self::NavigationOrder, Type::int()),
                new PropertyDefinition(self::NavigationBadge, Type::bool(), default: false),
                new PropertyDefinition(self::NavigationBadgeVariant, Type::string(), allowedValues: array_keys(NavigationPresets::BadgeVariants)),
            )
            ->withAllowedChildren([Source::class, Table::class, Form::class, Infolist::class, Action::class, ActionGroup::class, Relation::class, Page::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Model];
    }
}
