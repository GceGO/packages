<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Actions\Handlers\UpdateRecord;

/**
 * Updates one record, resolved through the blueprint's scoped data source:
 * ability "update" on that record, validated and writable input only.
 */
class Edit extends Action
{
    public static function requiresInput(): bool
    {
        return true;
    }

    protected static function defaultHandler(): string
    {
        return UpdateRecord::class;
    }

    protected static function typeDefaults(): array
    {
        return ['name' => 'edit', 'label' => 'Edit', 'icon' => 'actions.edit', 'ability' => 'update', 'intent' => ActionIntent::Secondary->value, 'placement' => [ActionPlacement::Row, ActionPlacement::Record], 'record' => true, 'success_message' => 'gcego-actions::messages.updated', 'order' => 10];
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
