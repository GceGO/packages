<?php

declare(strict_types=1);

namespace Gcego\Media\Uploads;

use Gcego\Media\Media;
use Illuminate\Database\Eloquent\Model;

/**
 * Where an upload goes: a collection, optionally a model to attach it to,
 * and an optional narrower definition (a MediaUpload field) whose limits
 * tighten the collection's. Built by trusted code.
 */
final readonly class UploadContext
{
    public function __construct(
        public string $collection,
        public ?Model $owner = null,
        public ?Media $rules = null,
        public ?string $alt = null,
    ) {}
}
