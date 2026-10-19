<?php

declare(strict_types=1);

namespace Gcego\Layouts;

use Gcego\Layouts\Exceptions\InvalidLayoutException;
use Gcego\Layouts\Presets\LayoutPresets;

/**
 * Validated gcego-layouts configuration.
 */
final readonly class LayoutsConfig
{
    public function __construct(
        public string $defaultMaxWidth = '7xl',
        public int $defaultGap = 6,
        public int $maxNodes = 400,
        public int $maxDepth = 12,
        public int $maxLivewireComponents = 12,
    ) {
        if (! isset(LayoutPresets::MaxWidths[$defaultMaxWidth])) {
            throw InvalidLayoutException::invalid('gcego-layouts.container.default_max_width', 'use one of '.implode(', ', array_keys(LayoutPresets::MaxWidths)).'.');
        }

        if (! in_array($defaultGap, LayoutPresets::GapSteps, true)) {
            throw InvalidLayoutException::invalid('gcego-layouts.grid.default_gap', 'use a spacing step ('.implode(', ', LayoutPresets::GapSteps).').');
        }

        foreach (['max_nodes' => $maxNodes, 'max_depth' => $maxDepth, 'max_livewire_components' => $maxLivewireComponents] as $key => $value) {
            if ($value < 1) {
                throw InvalidLayoutException::invalid('gcego-layouts.diagnostics.'.$key, 'must be a positive whole number.');
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $read = static function (string $section, string $key, mixed $default) use ($config): mixed {
            $values = $config[$section] ?? [];

            return is_array($values) && array_key_exists($key, $values) ? $values[$key] : $default;
        };

        $width = $read('container', 'default_max_width', '7xl');
        $gap = $read('grid', 'default_gap', 6);
        $limits = [];

        foreach (['max_nodes' => 400, 'max_depth' => 12, 'max_livewire_components' => 12] as $key => $default) {
            $value = $read('diagnostics', $key, $default);
            $limits[] = is_int($value) ? $value : throw InvalidLayoutException::invalid('gcego-layouts.diagnostics.'.$key, 'must be a positive whole number.');
        }

        return new self(
            is_string($width) ? $width : throw InvalidLayoutException::invalid('gcego-layouts.container.default_max_width', 'must be a string.'),
            is_int($gap) ? $gap : throw InvalidLayoutException::invalid('gcego-layouts.grid.default_gap', 'must be a whole number.'),
            ...$limits,
        );
    }
}
