<?php

declare(strict_types=1);

namespace Gcego\Swatches\Validation;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
