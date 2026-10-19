<?php

declare(strict_types=1);

namespace Gcego\Infolists\Tests\Fixtures;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
