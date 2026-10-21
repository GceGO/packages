<?php

declare(strict_types=1);

namespace Gcego\Components\Normalization;

use Gcego\Components\Contracts\RuntimeValue;
use Gcego\Components\Support\ValueDescriber;

/**
 * Stands in for a RuntimeValue in normalized output.
 *
 * Only the class name is kept, so closures, services and other runtime state
 * never leak into normalized definitions. Being an object, it cannot be
 * confused with ordinary array data.
 */
final readonly class RuntimePlaceholder
{
    public string $class;

    /**
     * @param  class-string<RuntimeValue>  $class
     */
    public function __construct(string $class)
    {
        $this->class = ValueDescriber::className($class);
    }
}
