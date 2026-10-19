<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Actions\Handlers\RestoreRecord;

/**
 * Restores the selected soft-deleted records: ability "restore" on every one of them.
 */
class BulkRestore extends BulkAction
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
        return ['name' => 'bulk-restore', 'label' => 'Restore selected', 'icon' => 'actions.restore', 'ability' => 'restore', 'intent' => ActionIntent::Secondary->value, 'placement' => ActionPlacement::Bulk, 'record' => false, 'success_message' => 'gcego-actions::messages.bulk_restored', 'order' => 80];
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
