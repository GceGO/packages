<?php

declare(strict_types=1);

namespace Gcego\Actions\Contracts;

use Gcego\Actions\Resolution\ResolvedAction;

/**
 * Optional for InputResolvers that present their own input (gcego/forms):
 * the ActionHost dialog then shows this HTML instead of plain Input fields.
 * Controls must bind to "<prefix>.<field>" (wire:model), and the HTML must be
 * built from trusted definitions with every value escaped.
 */
interface RendersInput
{
    /**
     * @param  array<string, mixed>  $state  The dialog's current (untrusted) input.
     * @param  array<string, list<string>>  $errors  Validation errors by field path.
     */
    public function renderInput(ResolvedAction $action, string|int|null $record, array $state, array $errors, string $prefix): string;
}
