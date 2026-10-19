<?php

declare(strict_types=1);

namespace Gcego\Media\Support;

final class FileIcon
{
    public static function for(string $kind): string
    {
        return match ($kind) {
            'image' => 'media.image',
            'video' => 'media.video',
            'audio' => 'media.audio',
            default => 'media.document',
        };
    }
}
