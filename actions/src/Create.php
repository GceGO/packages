<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Actions\Handlers\CreateRecord;

/**
 * Creates a record of the blueprint's source model:
 *
 *     new Create
 *     new Create('New subject', new Input(['name' => ['required', 'string', 'max:120']]))
 *
 * Ability "create" on the model class. Persists only validated input from an
 * InputResolver (Input rules, or gcego/forms), restricted to writable fields,
 * through Model::fill() ($fillable/$guarded apply). Without validated input it refuses to persist.
 */
class Create extends Action
{
    public static function requiresInput(): bool
    {
        return true;
    }

    protected static function defaultHandler(): string
    {
        return CreateRecord::class;
    }

    protected static function typeDefaults(): array
    {
        return ['name' => 'create', 'label' => 'Create', 'icon' => 'actions.create', 'ability' => 'create', 'intent' => ActionIntent::Primary->value, 'placement' => ActionPlacement::Header, 'record' => false, 'success_message' => 'gcego-actions::messages.created', 'order' => 0];
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
