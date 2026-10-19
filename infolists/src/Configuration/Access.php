<?php

declare(strict_types=1);

namespace Gcego\Infolists\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Fields\FieldRule;

/**
 * Entry-level read authorization through gcego/security, decided for the
 * current user and the displayed record:
 *
 *     new Text('national_id', 'National ID', new Access(read: new Permission('students.identity.view')))
 *     new Money('salary', 'Salary', new Access(read: new Permission('staff.pay.view'), mask: true))
 *
 * An unreadable entry's value is never read, rendered or serialized; with
 * $mask it shows a mask instead of disappearing.
 */
final readonly class Access implements ConfigurationNode
{
    public function __construct(public ?Requirement $read = null, public bool $mask = false) {}

    public function rule(string $path): FieldRule
    {
        return new FieldRule($path, read: $this->read, mask: $this->mask);
    }

    /**
     * @return array{read: string|null, mask: bool}
     */
    public function normalize(): array
    {
        return ['read' => $this->read?->describe(), 'mask' => $this->mask];
    }
}
