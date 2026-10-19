<?php

declare(strict_types=1);

namespace Gcego\Security\Fields;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Exceptions\InvalidPermissionException;

/**
 * Field-level access, for forms, tables and infolists:
 *
 *     new FieldRule('national_id', read: new Permission('students.identity.view'), write: new Permission('students.identity.update'), mask: true)
 *     new FieldRule('score', write: Permission::inTenant('grades.update'))
 *
 * Without $read, the field is readable whenever the record is. Without
 * $write, it is writable whenever it is readable. Unreadable fields are
 * hidden, or masked when $mask is true (the value is never sent).
 */
final readonly class FieldRule implements ConfigurationNode
{
    public const string NamePattern = '/^[A-Za-z_][A-Za-z0-9_]{0,63}(?:\.[A-Za-z_][A-Za-z0-9_]{0,63}){0,3}$/';

    public function __construct(
        public string $field,
        public ?Requirement $read = null,
        public ?Requirement $write = null,
        public bool $mask = false,
    ) {
        if (preg_match(self::NamePattern, $field) !== 1) {
            throw InvalidPermissionException::invalid('FieldRule', sprintf('"%s" is not a valid field name.', mb_substr($field, 0, 64)));
        }
    }

    /**
     * @return array{field: string, read: string|null, write: string|null, mask: bool}
     */
    public function normalize(): array
    {
        return ['field' => $this->field, 'read' => $this->read?->describe(), 'write' => $this->write?->describe(), 'mask' => $this->mask];
    }
}
