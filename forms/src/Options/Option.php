<?php

declare(strict_types=1);

namespace Gcego\Forms\Options;

/**
 * One choice of a Select, Radio or CheckboxList.
 */
final readonly class Option
{
    public function __construct(
        public string|int $value,
        public string $label,
        public bool $disabled = false,
    ) {}
}
