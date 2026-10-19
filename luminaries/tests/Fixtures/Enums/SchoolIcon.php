<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Tests\Fixtures\Enums;

use Gcego\Luminaries\Contracts\IconReference;

enum SchoolIcon: string implements IconReference
{
    case Terms = 'school.terms';
    case Logo = 'custom:gcego-logo';
}
