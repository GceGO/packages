<?php

declare(strict_types=1);

namespace Gcego\Forms\State;

use Gcego\Forms\Configuration\Access;
use Gcego\Forms\Schema\FieldEntry;
use Gcego\Forms\Schema\FormSchema;
use Gcego\Security\Fields\FieldAccess as Level;
use Gcego\Security\Fields\FieldDecisions;
use Gcego\Security\Fields\FieldRule;
use Gcego\Security\SecurityManager;

/**
 * Field-level authorization for one user and one record, from the fields'
 * Access nodes through gcego/security (one decision set per repeater scope).
 */
final readonly class FieldAccess
{
    /**
     * @param  array<string, FieldDecisions>  $scopes  Repeater template ("" for top level) => decisions.
     */
    private function __construct(private array $scopes) {}

    public static function decide(FormSchema $schema, mixed $record, SecurityManager $security): self
    {
        $grouped = [];

        foreach ($schema->entries as $entry) {
            $access = $entry->field->configurations()->first(Access::class);
            $grouped[$entry->repeater ?? ''][] = $access instanceof Access ? $access->rule($entry->name()) : $entry->name();
        }

        return new self(array_map(static fn (array $fields): FieldDecisions => $security->fields(array_values(array_filter($fields, static fn (mixed $field): bool => is_string($field) || $field instanceof FieldRule)), $record), $grouped));
    }

    public function level(FieldEntry $entry): Level
    {
        return ($this->scopes[$entry->repeater ?? ''] ?? null)?->access($entry->name()) ?? Level::Hidden;
    }

    public function readable(FieldEntry $entry): bool
    {
        return $this->level($entry)->canRead();
    }

    public function editable(FieldEntry $entry): bool
    {
        return $this->level($entry)->canWrite();
    }
}
