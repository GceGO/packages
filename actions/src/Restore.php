<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Actions\Handlers\RestoreRecord;

/**
 * Restores one soft-deleted record. The record is resolved explicitly among
 * trashed rows of the scoped source (normal sources never show them).
 * Ability "restore"; confirmation per gcego-actions.confirmation.restore.
 */
class Restore extends Action
{
    public static function confirmationKey(): string
    {
        return 'restore';
    }

    public static function trashed(): string
    {
        return 'only';
    }

    protected static function defaultHandler(): string
    {
        return RestoreRecord::class;
    }

    protected static function typeDefaults(): array
    {
        return ['name' => 'restore', 'label' => 'Restore', 'icon' => 'actions.restore', 'ability' => 'restore', 'intent' => ActionIntent::Secondary->value, 'placement' => [ActionPlacement::Row, ActionPlacement::Record], 'record' => true, 'success_message' => 'gcego-actions::messages.restored', 'order' => 80];
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
