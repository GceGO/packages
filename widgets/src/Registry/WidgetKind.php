<?php

declare(strict_types=1);

namespace Gcego\Widgets\Registry;

use Gcego\Registry\Contracts\DefinitionKind;
use Gcego\Widgets\Widget;
use InvalidArgumentException;

/**
 * The "widget" definition kind of gcego/registry: widgets are registered
 * and discovered (app/GCEGo/Widgets) like blueprints, under "widget:<name>".
 * Clients refer to widgets by that identity only, never by class.
 */
final readonly class WidgetKind implements DefinitionKind
{
    public const string Id = 'widget';

    public function id(): string
    {
        return self::Id;
    }

    public function contract(): string
    {
        return Widget::class;
    }

    public function name(object $definition): string
    {
        return $this->widget($definition)->widgetName();
    }

    public function validate(object $definition): array
    {
        return array_map(strval(...), $this->widget($definition)->validate()->errors());
    }

    public function metadata(object $definition): array
    {
        $widget = $this->widget($definition);
        $layout = $widget->layout();

        return [
            'title' => $widget->title(),
            'description' => $widget->description(),
            'type' => $widget::class,
            'icon' => is_string($icon = $widget->get(Widget::Icon)) ? $icon : null,
            'span' => (string) $layout['span'],
            'height' => $layout['height'],
            'interactive' => $widget->needsLivewire(),
        ];
    }

    private function widget(object $definition): Widget
    {
        return $definition instanceof Widget ? $definition : throw new InvalidArgumentException(sprintf('%s is not a widget.', $definition::class));
    }
}
