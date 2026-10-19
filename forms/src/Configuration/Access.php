<?php

declare(strict_types=1);

namespace Gcego\Forms\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Fields\FieldRule;

/**
 * Field-level authorization through gcego/security:
 *
 *     new TextInput('national_id', 'National ID', new Access(read: new Permission('students.identity.view'), write: Permission::inTenant('students.identity.update'), mask: true))
 *
 * Unreadable fields are never hydrated or sent to the browser (masked or
 * omitted); non-editable fields are rendered read-only and their submitted
 * values are ignored on the server.
 */
final readonly class Access implements ConfigurationNode
{
    public function __construct(
        public ?Requirement $read = null,
        public ?Requirement $write = null,
        public bool $mask = false,
    ) {}

    public function rule(string $field): FieldRule
    {
        return new FieldRule($field, $this->read, $this->write, $this->mask);
    }

    /**
     * @return array{read: string|null, write: string|null, mask: bool}
     */
    public function normalize(): array
    {
        return ['read' => $this->read?->describe(), 'write' => $this->write?->describe(), 'mask' => $this->mask];
    }
}
