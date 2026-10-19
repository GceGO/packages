<?php

declare(strict_types=1);

namespace Gcego\Cli\Contracts;

use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Generation\GenerationPlan;

/**
 * Produces the files of one kind of definition. A generator only plans:
 * it validates input, resolves paths and renders stubs; it never writes
 * (GenerationTransaction does) and never prompts (commands do).
 */
interface Generator
{
    /**
     * Stable identifier ("resource", "acme.report-card"): lowercase, digits, "-", "." .
     */
    public function id(): string;

    public function description(): string;

    /**
     * GCEGO packages that must be installed for this generator (e.g. ["gcego/workflows"]).
     *
     * @return list<string>
     */
    public function requires(): array;

    /**
     * Options this generator understands, name => description (for gcego:make and documentation).
     *
     * @return array<string, string>
     */
    public function options(): array;

    public function plan(GenerationContext $context): GenerationPlan;
}
