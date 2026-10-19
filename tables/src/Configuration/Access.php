<?php

declare(strict_types=1);

namespace Gcego\Tables\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Fields\FieldRule;

/**
 * Column-level read authorization through gcego/security:
 *
 *     new Text('national_id', 'National ID', new Access(read: new Permission('students.identity.view')))
 *     new Money('salary', 'Salary', new Access(read: new Permission('staff.pay.view'), mask: true))
 *
 * Decided once per request for the current user. An unreadable column is
 * removed: not rendered, searched, sorted, filtered or exported, and user
 * preferences or saved views cannot bring it back. With $mask it stays as a
 * column whose cells show a mask instead of values.
 */
final readonly class Access implements ConfigurationNode
{
    public function __construct(public ?Requirement $read = null, public bool $mask = false) {}

    public function rule(string $column): FieldRule
    {
        return new FieldRule($column, read: $this->read, mask: $this->mask);
    }

    /**
     * @return array{read: string|null, mask: bool}
     */
    public function normalize(): array
    {
        return ['read' => $this->read?->describe(), 'mask' => $this->mask];
    }
}
