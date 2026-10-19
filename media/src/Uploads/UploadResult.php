<?php

declare(strict_types=1);

namespace Gcego\Media\Uploads;

use Gcego\Media\Models\MediaRecord;

/**
 * The outcome of one file of an upload: the stored media, or the reason and messages it was refused.
 */
final readonly class UploadResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public string $name,
        public ?MediaRecord $media = null,
        public ?string $reason = null,
        public array $errors = [],
    ) {}

    public function successful(): bool
    {
        return $this->media !== null;
    }
}
