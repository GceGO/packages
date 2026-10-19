<?php

declare(strict_types=1);

namespace Gcego\Forms\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;

/**
 * Makes a custom action take its input from the blueprint's Form (Create and Edit always do):
 *
 *     new Action('reschedule', 'Reschedule', new Property(Action::Ability, 'update'), new Property(Action::Record, true), new UsesForm, new Handler(...))
 */
final readonly class UsesForm implements ConfigurationNode
{
    /**
     * @return array{form: true}
     */
    public function normalize(): array
    {
        return ['form' => true];
    }
}
