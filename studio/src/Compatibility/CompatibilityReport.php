<?php

declare(strict_types=1);

namespace Gcego\Studio\Compatibility;

/**
 * Platform and package compatibility. Problems block (incompatible or
 * missing); notices are informational (an unverifiable constraint).
 */
final readonly class CompatibilityReport
{
    /**
     * @param  array<string, string|null>  $versions  Component => installed version.
     * @param  list<string>  $problems
     * @param  list<string>  $notices
     */
    public function __construct(public array $versions, public array $problems, public array $notices) {}

    public function compatible(): bool
    {
        return $this->problems === [];
    }

    /**
     * @return array{compatible: bool, versions: array<string, string|null>, problems: list<string>, notices: list<string>}
     */
    public function toArray(): array
    {
        return ['compatible' => $this->compatible(), 'versions' => $this->versions, 'problems' => $this->problems, 'notices' => $this->notices];
    }
}
