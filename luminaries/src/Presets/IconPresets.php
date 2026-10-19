<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Presets;

/**
 * Default size and tone classes (referenced by config/luminaries.php and
 * scanned by gcego/chroma's @source, which reads only src/Presets).
 */
final class IconPresets
{
    public const array Sizes = [
        'xs' => 'size-3',
        'sm' => 'size-4',
        'md' => 'size-5',
        'lg' => 'size-6',
        'xl' => 'size-8',
        '2xl' => 'size-10',
    ];

    public const array Tones = [
        'primary' => 'text-gcego-primary',
        'secondary' => 'text-gcego-text',
        'muted' => 'text-gcego-text-muted',
        'success' => 'text-gcego-success',
        'warning' => 'text-gcego-warning',
        'danger' => 'text-gcego-danger',
        'info' => 'text-gcego-info',
    ];

    public const string Icon = 'shrink-0';
}
