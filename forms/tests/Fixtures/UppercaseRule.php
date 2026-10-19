<?php

declare(strict_types=1);

namespace Gcego\Forms\Tests\Fixtures;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class UppercaseRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strtoupper($value) !== $value) {
            $fail('The :attribute must be uppercase.');
        }
    }
}
