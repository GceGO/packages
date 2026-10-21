<?php

declare(strict_types=1);

namespace Gcego\Core\Events;

use Gcego\Core\Bootstrap\BootstrapResult;

final readonly class FrameworkBooted
{
    public function __construct(public BootstrapResult $result) {}
}
