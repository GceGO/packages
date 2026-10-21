<?php

declare(strict_types=1);

namespace Gcego\Components\Catalog;

use Gcego\Components\Actions\Button;
use Gcego\Components\Actions\ButtonGroup;
use Gcego\Components\Actions\Dropdown;
use Gcego\Components\Actions\DropdownItem;
use Gcego\Components\Component;
use Gcego\Components\DataDisplay\Avatar;
use Gcego\Components\DataDisplay\Badge;
use Gcego\Components\DataDisplay\CodeBlock;
use Gcego\Components\DataDisplay\DescriptionList;
use Gcego\Components\DataDisplay\Heading;
use Gcego\Components\DataDisplay\Icon;
use Gcego\Components\DataDisplay\KeyValueList;
use Gcego\Components\DataDisplay\ListItem;
use Gcego\Components\DataDisplay\ListView;
use Gcego\Components\DataDisplay\Progress;
use Gcego\Components\DataDisplay\Stat;
use Gcego\Components\DataDisplay\Table;
use Gcego\Components\DataDisplay\TableColumn;
use Gcego\Components\DataDisplay\Text;
use Gcego\Components\DataDisplay\Timeline;
use Gcego\Components\DataDisplay\TimelineItem;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Feedback\Alert;
use Gcego\Components\Feedback\Banner;
use Gcego\Components\Feedback\EmptyState;
use Gcego\Components\Feedback\Popover;
use Gcego\Components\Feedback\Skeleton;
use Gcego\Components\Feedback\Spinner;
use Gcego\Components\Feedback\Toast;
use Gcego\Components\Feedback\Tooltip;
use Gcego\Components\Forms\Checkbox;
use Gcego\Components\Forms\CodeEditor;
use Gcego\Components\Forms\ColorPicker;
use Gcego\Components\Forms\DatePicker;
use Gcego\Components\Forms\DateTimePicker;
use Gcego\Components\Forms\EmailInput;
use Gcego\Components\Forms\FileUpload;
use Gcego\Components\Forms\Form;
use Gcego\Components\Forms\HiddenInput;
use Gcego\Components\Forms\MultiSelect;
use Gcego\Components\Forms\NumberInput;
use Gcego\Components\Forms\PasswordInput;
use Gcego\Components\Forms\Radio;
use Gcego\Components\Forms\RangeInput;
use Gcego\Components\Forms\RichEditor;
use Gcego\Components\Forms\SearchInput;
use Gcego\Components\Forms\Select;
use Gcego\Components\Forms\TelInput;
use Gcego\Components\Forms\Textarea;
use Gcego\Components\Forms\TextInput;
use Gcego\Components\Forms\TimePicker;
use Gcego\Components\Forms\Toggle;
use Gcego\Components\Forms\UrlInput;
use Gcego\Components\Layout\Accordion;
use Gcego\Components\Layout\AccordionItem;
use Gcego\Components\Layout\Card;
use Gcego\Components\Layout\Column;
use Gcego\Components\Layout\Container;
use Gcego\Components\Layout\Drawer;
use Gcego\Components\Layout\Fieldset;
use Gcego\Components\Layout\Grid;
use Gcego\Components\Layout\Group;
use Gcego\Components\Layout\Item;
use Gcego\Components\Layout\Modal;
use Gcego\Components\Layout\Panel;
use Gcego\Components\Layout\Row;
use Gcego\Components\Layout\Section;
use Gcego\Components\Layout\Splitter;
use Gcego\Components\Layout\Stack;
use Gcego\Components\Layout\Tab;
use Gcego\Components\Layout\Tabs;
use Gcego\Components\Layout\Wizard;
use Gcego\Components\Layout\WizardStep;
use Gcego\Components\Media\Audio;
use Gcego\Components\Media\Calendar;
use Gcego\Components\Media\Chart;
use Gcego\Components\Media\Embed;
use Gcego\Components\Media\Image;
use Gcego\Components\Media\Map;
use Gcego\Components\Media\Video;
use Gcego\Components\Navigation\Breadcrumb;
use Gcego\Components\Navigation\Breadcrumbs;
use Gcego\Components\Navigation\Link;
use Gcego\Components\Navigation\Menu;
use Gcego\Components\Navigation\MenuItem;
use Gcego\Components\Navigation\Pagination;
use ReflectionClass;

/** Provider-scoped registry of trusted, named component factories. */
final class ComponentCatalog
{
    /** @var array<string, class-string<Component>> */
    private array $components = [
        'section' => Section::class,
        'group' => Group::class,
        'item' => Item::class,
        'container' => Container::class,
        'grid' => Grid::class,
        'stack' => Stack::class,
        'row' => Row::class,
        'column' => Column::class,
        'card' => Card::class,
        'panel' => Panel::class,
        'tabs' => Tabs::class,
        'tab' => Tab::class,
        'accordion' => Accordion::class,
        'accordionItem' => AccordionItem::class,
        'fieldset' => Fieldset::class,
        'modal' => Modal::class,
        'drawer' => Drawer::class,
        'splitter' => Splitter::class,
        'wizard' => Wizard::class,
        'wizardStep' => WizardStep::class,
        'form' => Form::class,
        'textInput' => TextInput::class,
        'textarea' => Textarea::class,
        'numberInput' => NumberInput::class,
        'emailInput' => EmailInput::class,
        'passwordInput' => PasswordInput::class,
        'searchInput' => SearchInput::class,
        'urlInput' => UrlInput::class,
        'telInput' => TelInput::class,
        'select' => Select::class,
        'multiSelect' => MultiSelect::class,
        'checkbox' => Checkbox::class,
        'radio' => Radio::class,
        'toggle' => Toggle::class,
        'datePicker' => DatePicker::class,
        'dateTimePicker' => DateTimePicker::class,
        'timePicker' => TimePicker::class,
        'fileUpload' => FileUpload::class,
        'hiddenInput' => HiddenInput::class,
        'colorPicker' => ColorPicker::class,
        'rangeInput' => RangeInput::class,
        'richEditor' => RichEditor::class,
        'codeEditor' => CodeEditor::class,
        'table' => Table::class,
        'tableColumn' => TableColumn::class,
        'text' => Text::class,
        'heading' => Heading::class,
        'badge' => Badge::class,
        'avatar' => Avatar::class,
        'icon' => Icon::class,
        'stat' => Stat::class,
        'keyValueList' => KeyValueList::class,
        'descriptionList' => DescriptionList::class,
        'listView' => ListView::class,
        'listItem' => ListItem::class,
        'progress' => Progress::class,
        'timeline' => Timeline::class,
        'timelineItem' => TimelineItem::class,
        'codeBlock' => CodeBlock::class,
        'menu' => Menu::class,
        'menuItem' => MenuItem::class,
        'breadcrumbs' => Breadcrumbs::class,
        'breadcrumb' => Breadcrumb::class,
        'pagination' => Pagination::class,
        'link' => Link::class,
        'alert' => Alert::class,
        'toast' => Toast::class,
        'banner' => Banner::class,
        'emptyState' => EmptyState::class,
        'skeleton' => Skeleton::class,
        'spinner' => Spinner::class,
        'tooltip' => Tooltip::class,
        'popover' => Popover::class,
        'button' => Button::class,
        'buttonGroup' => ButtonGroup::class,
        'dropdown' => Dropdown::class,
        'dropdownItem' => DropdownItem::class,
        'image' => Image::class,
        'video' => Video::class,
        'audio' => Audio::class,
        'embed' => Embed::class,
        'map' => Map::class,
        'chart' => Chart::class,
        'calendar' => Calendar::class,
    ];

    public function __construct()
    {
        Components::getFacadeRoot();
    }

    /** @param class-string<Component> $component */
    public function register(string $alias, string $component): void
    {
        if (preg_match('/\A[a-z][a-zA-Z0-9]*\z/', $alias) !== 1 || method_exists(Component::class, $alias)) {
            throw InvalidConfigurationException::invalidDefinition('Catalog aliases must be camelCase identifiers without a reserved Component method name.');
        }

        foreach ($this->components as $registered => $class) {
            if (strcasecmp($registered, $alias) === 0) {
                throw InvalidConfigurationException::invalidDefinition('A component with this catalog alias is already registered.');
            }
        }

        if (! is_a($component, Component::class, true) || ! (new ReflectionClass($component))->isInstantiable()) {
            throw InvalidConfigurationException::invalidDefinition('Catalog entries must be concrete component classes with public constructors.');
        }

        $this->components[$alias] = $component;
    }

    /** @return class-string<Component> */
    public function resolve(string $alias): string
    {
        return $this->components[$alias] ?? throw InvalidConfigurationException::invalidDefinition(sprintf('Unknown component catalog alias "%s".', $alias));
    }

    /** @return array<string, class-string<Component>> */
    public function all(): array
    {
        return $this->components;
    }
}
