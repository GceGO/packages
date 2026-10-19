<?php

declare(strict_types=1);

namespace Gcego\Modules\Relationships;

use Gcego\Actions\Action;
use Gcego\Actions\ActionIntent;
use Gcego\Actions\ActionPlacement;

/**
 * Detach a related record from the parent (BelongsToMany relations only).
 * The record itself is kept; it must be related to this parent and
 * viewable, and the parent must allow "update". Asks for confirmation.
 */
class Detach extends Action
{
    protected static function typeDefaults(): array
    {
        return ['name' => 'detach', 'label' => 'Detach', 'icon' => 'actions.detach', 'ability' => 'view', 'intent' => ActionIntent::Destructive->value, 'placement' => [ActionPlacement::Row, ActionPlacement::Record], 'record' => true, 'confirmation' => true, 'order' => 85];
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
