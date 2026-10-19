<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}
