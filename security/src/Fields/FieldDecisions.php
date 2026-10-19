<?php

declare(strict_types=1);

namespace Gcego\Security\Fields;

/**
 * The resolved access of every declared field, for one user and one record.
 */
final readonly class FieldDecisions
{
    /**
     * @param  array<string, FieldAccess>  $fields
     */
    public function __construct(public array $fields) {}

    public function access(string $field): FieldAccess
    {
        return $this->fields[$field] ?? FieldAccess::Hidden;
    }

    /**
     * @return list<string>
     */
    public function readable(): array
    {
        return array_keys(array_filter($this->fields, static fn (FieldAccess $access): bool => $access->canRead()));
    }

    /**
     * @return list<string>
     */
    public function editable(): array
    {
        return array_keys(array_filter($this->fields, static fn (FieldAccess $access): bool => $access->canWrite()));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_map(static fn (FieldAccess $access): string => strtolower($access->name), $this->fields);
    }
}
