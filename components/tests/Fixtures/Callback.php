<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

use Closure;
use Gcego\Components\Contracts\RuntimeValue;

final readonly class Callback implements RuntimeValue
{
    public function __construct(public Closure $callback) {}
}
