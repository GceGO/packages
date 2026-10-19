<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Actions\Handlers\ViewRecord;

/**
 * Read-only: resolves and authorizes ("view") one record and returns a view
 * intent for a viewer (future gcego/infolists). Never mutates, never runs in a transaction.
 */
class View extends Action
{
    protected static function defaultHandler(): string
    {
        return ViewRecord::class;
    }

    protected static function typeDefaults(): array
    {
        return ['name' => 'view', 'label' => 'View', 'icon' => 'actions.view', 'ability' => 'view', 'intent' => ActionIntent::Secondary->value, 'placement' => [ActionPlacement::Row], 'record' => true, 'transaction' => false, 'order' => 5];
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
