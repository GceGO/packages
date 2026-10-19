<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Actions\Handlers\DeleteRecord;

/**
 * Deletes the selected records: ability "delete" on every one of them.
 */
class BulkDelete extends BulkAction
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
        return ['name' => 'bulk-delete', 'label' => 'Delete selected', 'icon' => 'actions.delete', 'ability' => 'delete', 'intent' => ActionIntent::Destructive->value, 'placement' => ActionPlacement::Bulk, 'record' => false, 'success_message' => 'gcego-actions::messages.bulk_deleted', 'order' => 90];
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
