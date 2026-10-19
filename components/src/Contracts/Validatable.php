<?php

declare(strict_types=1);

namespace Gcego\Components\Contracts;

use Gcego\Components\Exceptions\InvalidComponentException;
use Gcego\Components\Validation\ValidationResult;

interface Validatable
{
    /**
     * Collect every validation error without throwing.
     */
    public function validate(): ValidationResult;

    /**
     * Strict mode: return the instance unchanged when valid.
     *
     * @throws InvalidComponentException
     */
    public function assertValid(): static;
}
