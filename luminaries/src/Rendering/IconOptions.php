<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Rendering;

/**
 * How one icon is rendered.
 *
 * $size is a size token ("sm", "lg"…); null uses the configured default and
 * "none" adds no size class. A $label makes the icon meaningful (role="img"
 * with an accessible name); without one, or when $decorative is true, the
 * icon is hidden from assistive technology.
 */
final readonly class IconOptions
{
    public const string NoSize = 'none';

    /**
     * @param  array<array-key, mixed>  $attributes  Extra attributes, checked by AttributeGuard.
     */
    public function __construct(
        public ?string $size = null,
        public ?string $tone = null,
        public string $class = '',
        public ?string $label = null,
        public bool $decorative = false,
        public ?float $strokeWidth = null,
        public array $attributes = [],
    ) {}

    public function isDecorative(): bool
    {
        return $this->decorative || $this->label === null || trim($this->label) === '';
    }
}
