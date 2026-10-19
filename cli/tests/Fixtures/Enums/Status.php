<?php

declare(strict_types=1);

namespace Gcego\Cli\Tests\Fixtures\Enums;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}
