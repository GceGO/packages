<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Support;

/**
 * Descriptive information about an icon provider, shown by the CLI and the explorer.
 */
final readonly class ProviderMetadata
{
    /**
     * @param  string  $driver  blade-icons, custom or inline.
     * @param  string  $style  outline, solid, mini, micro or custom.
     * @param  bool  $strokeWidth  Whether the icons are stroke-drawn so a stroke width can be applied.
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $driver,
        public string $style,
        public bool $strokeWidth,
        public ?string $source = null,
        public ?string $path = null,
    ) {}

    /**
     * @return array{name: string, label: string, driver: string, style: string, stroke_width: bool, source: ?string, path: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'driver' => $this->driver,
            'style' => $this->style,
            'stroke_width' => $this->strokeWidth,
            'source' => $this->source,
            'path' => $this->path,
        ];
    }
}
