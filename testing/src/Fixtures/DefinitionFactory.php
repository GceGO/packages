<?php

declare(strict_types=1);

namespace Gcego\Testing\Fixtures;

use Gcego\Actions\Action;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Create;
use Gcego\Actions\Delete;
use Gcego\Actions\Edit;
use Gcego\Blueprints\Blueprint;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Property;
use Gcego\Data\Search\Search;
use Gcego\Data\Source;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Fields\Select;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Components\Section;
use Gcego\Layouts\Layout;
use Gcego\Modules\Module;
use Gcego\Modules\Resource;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Navigation;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Types\DashboardPage;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Panel;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Requirements\Ability;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;
use Gcego\Testing\Exceptions\MissingPackageException;
use Gcego\Widgets\Data\Value;
use Gcego\Widgets\Statistics\Stat;
use Illuminate\Database\Eloquent\Model;

/**
 * Representative definitions built with each package's public API, for
 * tests that need "a form", "a table" or "a resource" and not a specific
 * one. Every method needs the package that defines the class; a missing
 * package raises MissingPackageException naming it.
 */
final class DefinitionFactory
{
    public function textInput(string $name = 'name', string $label = 'Name', bool $required = true): TextInput
    {
        $this->needs(TextInput::class, 'gcego/forms');

        return new TextInput($name, $label, new Property(TextInput::Required, $required));
    }

    /**
     * @param  array<string, string>  $options  value => label
     */
    public function select(string $name = 'status', string $label = 'Status', array $options = ['draft' => 'Draft', 'published' => 'Published']): Select
    {
        $this->needs(Select::class, 'gcego/forms');

        return new Select($name, $label, new Options($options));
    }

    public function form(ComponentInterface ...$fields): Form
    {
        $this->needs(Form::class, 'gcego/forms');

        return new Form(...($fields === [] ? [$this->textInput(), $this->textInput('code', 'Code', false)] : $fields));
    }

    public function column(string $name = 'name', string $label = 'Name', bool $searchable = true, bool $sortable = true): Text
    {
        $this->needs(Text::class, 'gcego/tables');

        return new Text($name, $label, new Property(Text::Searchable, $searchable), new Property(Text::Sortable, $sortable));
    }

    public function table(ComponentInterface ...$columns): Table
    {
        $this->needs(Table::class, 'gcego/tables');

        return new Table(...($columns === [] ? [$this->column(), $this->column('code', 'Code', false, false)] : $columns));
    }

    /**
     * @param  class-string  $model
     * @param  list<string>  $search
     */
    public function source(string $model, array $search = ['name']): Source
    {
        $this->needs(Source::class, 'gcego/data');

        return $search === [] ? new Source($model) : new Source($model, new Search(...$search));
    }

    public function infolist(ComponentInterface ...$entries): Infolist
    {
        $this->needs(Infolist::class, 'gcego/infolists');

        return new Infolist(...($entries === [] ? [new TextEntry('name', 'Name'), new TextEntry('code', 'Code')] : $entries));
    }

    /**
     * A record action that runs $handler (true when omitted) for users the
     * policy allows $ability.
     */
    public function action(string $name = 'publish', string $label = 'Publish', string $ability = 'update', ?\Closure $handler = null): Action
    {
        $this->needs(Action::class, 'gcego/actions');

        return new Action($name, $label, new Property(Action::Ability, $ability), new Property(Action::Record, true), new Handler($handler ?? static fn (): bool => true));
    }

    /**
     * A complete CRUD blueprint for a model: source with search, table,
     * form, infolist and the create, edit and delete actions, opened to
     * users the model policy allows "viewAny".
     *
     * @param  class-string<Model>  $model
     */
    public function resource(string $name, string $model): Blueprint
    {
        $this->needs(Blueprint::class, 'gcego/blueprints');

        return new Blueprint($name, new Ability('viewAny', $model), $this->source($model), $this->table(), $this->form(), $this->infolist(), new Create, new Edit, new Delete);
    }

    public function stat(string $label = 'Students', int|float|string $value = 0, ?Requirement $requirement = null): Stat
    {
        $this->needs(Stat::class, 'gcego/widgets');

        return new Stat($label, new Value(static fn (): int|float|string => $value), $requirement ?? new Authenticated);
    }

    public function layout(string $name = 'dashboard'): Layout
    {
        $this->needs(Layout::class, 'gcego/layouts');

        return new Layout($name, new Grid(new Section('Summary'), new Section('Activity')));
    }

    /**
     * @param  array<string, string>  $items  name => route name
     */
    public function navigation(string $name = 'admin', array $items = ['dashboard' => 'dashboard']): Navigation
    {
        $this->needs(Navigation::class, 'gcego/navigation');
        $nodes = [];

        foreach ($items as $item => $route) {
            $nodes[] = new Item(new Property(Item::Name, $item), new Property(Item::Label, ucfirst($item)), new Property(Item::Route, $route));
        }

        return new Navigation($name, ...$nodes);
    }

    public function page(string $name = 'overview', string $title = 'Overview'): DashboardPage
    {
        $this->needs(DashboardPage::class, 'gcego/pages');

        return new DashboardPage(new Property(DashboardPage::Name, $name), new Property(DashboardPage::Title, $title), new Authenticated, new Section('Summary', new Content('Welcome.')));
    }

    /**
     * A module with one CRUD resource for the model.
     *
     * @param  class-string  $model
     */
    public function module(string $name, string $model): Module
    {
        $this->needs(Module::class, 'gcego/modules');

        return new Module(new Property(Module::Name, $name), new Resource($this->source($model), $this->table(), $this->form(), $this->infolist(), new Create, new Edit, new Delete));
    }

    /**
     * @param  list<string>  $modules
     */
    public function panel(string $name = 'admin', string $path = 'admin', ?Requirement $access = null, array $modules = []): Panel
    {
        $this->needs(Panel::class, 'gcego/panels');

        return new Panel(new Property(Panel::Name, $name), new Property(Panel::Path, $path), $access ?? new Authenticated, ...array_map(static fn (string $module): ModuleReference => new ModuleReference($module), $modules));
    }

    private function needs(string $class, string $package): void
    {
        if (! class_exists($class)) {
            throw MissingPackageException::for($package, 'DefinitionFactory');
        }
    }
}
