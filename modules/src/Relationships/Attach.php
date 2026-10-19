<?php

declare(strict_types=1);

namespace Gcego\Modules\Relationships;

use Gcego\Actions\Action;
use Gcego\Actions\ActionIntent;
use Gcego\Actions\ActionPlacement;

/**
 * Attach an existing record to the parent (BelongsToMany relations only):
 *
 *     new Relation('tags', new Property(Relation::Resource, 'tags'), new Attach, new Detach)
 *
 * Declared on a Relation; the module adds what it needs on its pages: the
 * key input, the parent "update" requirement and the handler that looks the
 * record up in scope (view) before attaching it.
 */
class Attach extends Action
{
    protected static function typeDefaults(): array
    {
        return ['name' => 'attach', 'label' => 'Attach', 'icon' => 'actions.attach', 'intent' => ActionIntent::Secondary->value, 'placement' => [ActionPlacement::Header], 'record' => false, 'order' => 20];
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
