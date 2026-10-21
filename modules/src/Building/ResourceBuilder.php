<?php

declare(strict_types=1);

namespace Gcego\Modules\Building;

use Closure;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Contracts\Buildable;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Properties\Property;
use Gcego\Data\Sorting\Sort;
use Gcego\Data\Sorting\SortDirection;
use Gcego\Data\Source;
use Gcego\Data\Support\Callback;
use Gcego\Forms\Form;
use Gcego\Infolists\Infolist;
use Gcego\Modules\Resource;
use Gcego\Tables\Table;

/**
 * The fluent form of a Resource:
 *
 *     Resource::define('teachers', Teacher::class)
 *         ->icon(Alias::NavigationTeachers)
 *         ->navigationBadge(true, 'success')
 *         ->recordTitle('full_name')
 *         ->source(fn (Builder $query) => $query->withCount('schoolClasses'), sort: 'full_name')
 *         ->table(fn (TableBuilder $table) => $table->columns([...]))
 *         ->form(fn (FormBuilder $form) => $form->section('Details')->schema([...]))
 *         ->infolist(fn (InfolistBuilder $infolist) => $infolist->section('Teacher')->schema([...]))
 *         ->actions([Create::define(), Edit::define(), Delete::define()])
 *
 * define() takes the resource name and model (or just the model). A form or
 * infolist callback may return a layout; it is wrapped in the Form or
 * Infolist. Every Resource property is available by name.
 */
class ResourceBuilder extends ComponentBuilder
{
    /**
     * The records: the resource's model, narrowed by a trusted query constraint and sorted by default.
     */
    public function source(?Closure $query = null, Sort|string|null $sort = null, SortDirection|string $direction = SortDirection::Ascending, Property|Sort ...$items): static
    {
        $model = $this->propertyValue(Resource::Model);

        if (! is_string($model)) {
            throw InvalidBuilderException::invalidValue($this->component, 'source', 'declare the model first: Resource::define(\'name\', Model::class).');
        }

        $source = Source::define(
            $model,
            is_string($sort) ? new Sort($sort, $direction) : $sort,
            $query === null ? null : new Property(Source::Query, new Callback($query)),
            ...$items,
        );

        return $this->replaceChild(Source::class, $source);
    }

    /**
     * Show the number of records the user may see in the navigation, in the given color.
     */
    public function navigationBadge(bool $enabled = true, ?string $variant = null): static
    {
        $builder = $this->set(Resource::NavigationBadge, $enabled);

        return $variant === null ? $builder : $builder->set(Resource::NavigationBadgeVariant, $variant);
    }

    /**
     * The index page's table: a Table (builder), or a callback that configures one.
     *
     * @param  Closure(ComponentBuilder): (ComponentInterface|Buildable)|ComponentInterface|Buildable  $table
     */
    public function table(Closure|ComponentInterface|Buildable $table): static
    {
        return $this->part(Table::class, $table, 'table');
    }

    /**
     * The create and edit form: a Form (builder), a layout, or a callback returning either.
     *
     * @param  Closure(ComponentBuilder): (ComponentInterface|Buildable)|ComponentInterface|Buildable  $form
     */
    public function form(Closure|ComponentInterface|Buildable $form): static
    {
        return $this->part(Form::class, $form, 'form');
    }

    /**
     * The view page's infolist: an Infolist (builder), a layout, or a callback returning either.
     *
     * @param  Closure(ComponentBuilder): (ComponentInterface|Buildable)|ComponentInterface|Buildable  $infolist
     */
    public function infolist(Closure|ComponentInterface|Buildable $infolist): static
    {
        return $this->part(Infolist::class, $infolist, 'infolist');
    }

    /**
     * Resource actions: Create and Edit add their pages; others appear on the list and record pages.
     *
     * @param  iterable<ComponentInterface|Buildable|null>  $actions
     */
    public function actions(iterable $actions): static
    {
        return $this->schema($actions);
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return array<int|string, mixed>
     */
    protected function prepare(array $items): array
    {
        if (isset($items[0], $items[1]) && is_string($items[0]) && is_string($items[1]) && ! class_exists($items[0])) {
            $name = $items[0];
            $model = $items[1];
            unset($items[0], $items[1]);

            return [new Property(Resource::Name, $name), new Property(Resource::Model, $model), ...$items];
        }

        return $items;
    }

    /**
     * @param  class-string<ComponentInterface>  $class
     */
    private function part(string $class, Closure|ComponentInterface|Buildable $part, string $method): static
    {
        if ($part instanceof Closure) {
            $existing = $this->childOf($class);
            $part = $part($existing instanceof ComponentBuilder ? $existing : $class::define());

            if (! $part instanceof ComponentInterface && ! $part instanceof Buildable) {
                throw InvalidBuilderException::invalidValue($this->component, $method, 'the callback must return a component or a builder.');
            }
        }

        if (! self::produces($part, $class)) {
            if ($class === Table::class) {
                throw InvalidBuilderException::invalidValue($this->component, $method, 'expected a Table.');
            }

            $part = $class::define()->schema([$part]);
        }

        return $this->replaceChild($class, $part);
    }
}
