<?php

declare(strict_types=1);

namespace Gcego\Widgets\Records;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Infolists\InfolistManager;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Widgets\Rendering\WidgetRenderFrame;
use Gcego\Widgets\Widget;
use Gcego\Widgets\WidgetsConfig;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * A record's details from a registered blueprint's Infolist (gcego/infolists),
 * inside a widget card:
 *
 *     new RecordSummary('Selected student', new Property(RecordSummary::Blueprint, 'students'))
 *
 * The record key comes from the host (WidgetManager::render(..., record: $id)
 * or <livewire:gcego-widget widget="…" :record="$id">), never from client
 * state. gcego/infolists resolves, scopes and authorizes the record and
 * decides entry access; nothing is formatted twice.
 */
final class RecordSummary extends Widget
{
    public const string Blueprint = 'blueprint';

    public function view(): string
    {
        return 'gcego-widgets::records.summary';
    }

    public function blueprint(): string
    {
        $blueprint = $this->stringProperty(self::Blueprint) ?? '';

        return str_contains($blueprint, ':') ? $blueprint : 'blueprint:'.$blueprint;
    }

    public function viewData(WidgetDataResult $result, WidgetsConfig $config, WidgetRenderFrame $frame): array
    {
        if ($frame->record === null) {
            return ['details' => null, 'empty' => true];
        }

        try {
            $details = app(InfolistManager::class)->render($this->blueprint(), $frame->record);
        } catch (AuthorizationException|HttpExceptionInterface) {
            $details = null;
        }

        return ['details' => $details, 'empty' => $details === null];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Blueprint, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^(?:blueprint:)?[a-z][a-z0-9._-]{0,127}$/', $value) === 1, ruleDescription: 'a blueprint name'),
        );
    }
}
