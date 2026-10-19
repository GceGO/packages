<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Actions\Handlers\ForceDeleteRecord;

/**
 * Permanently deletes one soft-deleted record (it must be trashed first).
 * Ability "forceDelete"; confirmation required (gcego-actions.confirmation.force_delete).
 */
class ForceDelete extends Action
{
    public static function confirmationKey(): string
    {
        return 'force_delete';
    }

    public static function trashed(): string
    {
        return 'only';
    }

    protected static function defaultHandler(): string
    {
        return ForceDeleteRecord::class;
    }

    protected static function typeDefaults(): array
    {
        return ['name' => 'force-delete', 'label' => 'Delete permanently', 'icon' => 'actions.force-delete', 'ability' => 'forceDelete', 'intent' => ActionIntent::Destructive->value, 'placement' => [ActionPlacement::Row, ActionPlacement::Record], 'record' => true, 'success_message' => 'gcego-actions::messages.force_deleted', 'order' => 95];
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
