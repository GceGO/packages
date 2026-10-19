<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Actions\Handlers\DeleteRecord;

/**
 * Deletes one record (soft delete when the model uses SoftDeletes).
 * Ability "delete"; confirmation required by default (gcego-actions.confirmation.delete).
 * Confirmation is a safety step, never a substitute: execution authorizes again.
 */
class Delete extends Action
{
    public static function confirmationKey(): string
    {
        return 'delete';
    }

    protected static function defaultHandler(): string
    {
        return DeleteRecord::class;
    }

    protected static function typeDefaults(): array
    {
        return ['name' => 'delete', 'label' => 'Delete', 'icon' => 'actions.delete', 'ability' => 'delete', 'intent' => ActionIntent::Destructive->value, 'placement' => [ActionPlacement::Row, ActionPlacement::Record], 'record' => true, 'success_message' => 'gcego-actions::messages.deleted', 'order' => 90];
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
