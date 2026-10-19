<?php

declare(strict_types=1);

namespace Gcego\Forms\Layout;

/**
 * One step of a Wizard: new Step('Subject', ...fields)
 */
final class Step extends LayoutComponent
{
    public function view(): string
    {
        return 'gcego-forms::layouts.step';
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
