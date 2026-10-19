<?php

declare(strict_types=1);

namespace Gcego\Media\Uploads;

/**
 * A validated local file: its display name, detected type, storage extension, size and image size.
 */
final readonly class InspectedFile
{
    /**
     * @param  array{width: int, height: int, type: string}|null  $image
     */
    public function __construct(
        public string $path,
        public string $name,
        public string $type,
        public string $extension,
        public int $size,
        public ?array $image = null,
    ) {}
}
