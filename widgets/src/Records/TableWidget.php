<?php

declare(strict_types=1);

namespace Gcego\Widgets\Records;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Widgets\Presets\WidgetPresets;
use Gcego\Widgets\Rendering\WidgetRenderFrame;
use Gcego\Widgets\Widget;
use Gcego\Widgets\WidgetsConfig;

/**
 * A registered blueprint's Table (gcego/tables) inside a widget card, with
 * a bounded height:
 *
 *     new TableWidget('Overdue invoices', new Property(TableWidget::Blueprint, 'overdue_invoices'), new Property(TableWidget::MaxHeight, 'md'))
 *
 * It mounts the table package's own Livewire host, so the table's
 * authorization, data scopes, search, sorting and actions all apply and
 * no table logic is duplicated.
 */
final class TableWidget extends Widget
{
    public const string Blueprint = 'blueprint';

    /** sm | md | lg: the scroll height of the table area. */
    public const string MaxHeight = 'max_height';

    public function view(): string
    {
        return 'gcego-widgets::records.table';
    }

    public function blueprint(): string
    {
        $blueprint = $this->stringProperty(self::Blueprint) ?? '';

        return str_contains($blueprint, ':') ? $blueprint : 'blueprint:'.$blueprint;
    }

    public function viewData(WidgetDataResult $result, WidgetsConfig $config, WidgetRenderFrame $frame): array
    {
        return ['empty' => false];
    }

    public function heightClass(): string
    {
        return WidgetPresets::TableHeights[$this->stringProperty(self::MaxHeight) ?? 'md'] ?? WidgetPresets::TableHeights['md'];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Blueprint, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^(?:blueprint:)?[a-z][a-z0-9._-]{0,127}$/', $value) === 1, ruleDescription: 'a blueprint name'),
            new PropertyDefinition(self::MaxHeight, Type::string(), default: 'md', allowedValues: ['sm', 'md', 'lg']),
        );
    }
}
