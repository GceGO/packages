<?php

declare(strict_types=1);

namespace Gcego\Forms\State;

use Gcego\Forms\Fields\ChoiceField;
use Gcego\Forms\Fields\Hidden;
use Gcego\Forms\Fields\PasswordInput;
use Gcego\Forms\Fields\Repeater;
use Gcego\Forms\FormsConfig;
use Gcego\Forms\Options\OptionsResolver;
use Gcego\Forms\Schema\FieldEntry;
use Gcego\Forms\Schema\FormSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Trusted data (a record, or defaults) to client form state.
 *
 * Only what the browser may see is included: unreadable fields (Access),
 * Hidden fields and passwords are never hydrated, so they never reach the
 * Livewire snapshot. Record values go through Eloquent (casts, accessors).
 */
final readonly class StateHydrator
{
    public function __construct(private FormsConfig $config) {}

    /**
     * @return array<string, mixed>
     */
    public function hydrate(FormSchema $schema, FieldAccess $access, ?Model $record = null): array
    {
        return $this->level($schema, $access, null, $record === null ? null : ['record' => $record], $schema->form->formName());
    }

    /**
     * @param  array<array-key, mixed>|null  $source
     * @return array<string, mixed>
     */
    private function level(FormSchema $schema, FieldAccess $access, ?string $repeater, ?array $source, string $form): array
    {
        $state = [];

        foreach ($schema->children($repeater) as $entry) {
            $field = $entry->field;

            if ($field instanceof Hidden || ! $access->readable($entry)) {
                continue;
            }

            if ($field instanceof PasswordInput) {
                $state[$entry->name()] = null;

                if ($field->isConfirmed()) {
                    $state[$entry->name().'_confirmation'] = null;
                }

                continue;
            }

            $state[$entry->name()] = $source === null ? $field->emptyState() : $field->hydrate($this->value($entry, $source), $this->config);

            if ($field instanceof Repeater && is_array($state[$entry->name()])) {
                foreach ($state[$entry->name()] as $key => $item) {
                    $state[$entry->name()][$key] = $this->level($schema, $access, $entry->path, $source === null ? null : (is_array($item) ? $item : []), $form);
                }
            }
        }

        return $state;
    }

    /**
     * @param  array<array-key, mixed>  $source
     */
    private function value(FieldEntry $entry, array $source): mixed
    {
        $record = $source['record'] ?? null;

        if (! $record instanceof Model) {
            return $source[$entry->name()] ?? $entry->field->emptyState();
        }

        $field = $entry->field;
        $relationship = $field instanceof ChoiceField ? $field->relationship() : null;

        if ($relationship !== null && OptionsResolver::related($record::class, $relationship)[1]) {
            $relation = $record->{$relationship->name}();

            return $relation instanceof BelongsToMany ? $relation->pluck($relation->getRelated()->getQualifiedKeyName())->all() : [];
        }

        return $record->exists ? $field->currentValue($record) : $field->emptyState();
    }
}
