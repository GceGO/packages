<?php

declare(strict_types=1);

namespace Gcego\Forms\Layout;

/**
 * A multi-step form: one Step at a time; "Next" validates the current step on
 * the server; submitting validates every step again.
 *
 *     new Wizard(new Step('Subject', ...), new Step('Objectives', ...))
 */
final class Wizard extends LayoutComponent
{
    public function view(): string
    {
        return 'gcego-forms::layouts.wizard';
    }

    /**
     * @return list<Step>
     */
    public function steps(): array
    {
        return array_values(array_filter($this->children()->all(), static fn (mixed $child): bool => $child instanceof Step));
    }

    protected static function allowedChildren(): array
    {
        return [Step::class];
    }
}
