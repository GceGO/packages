<?php

declare(strict_types=1);

namespace Gcego\Media\Columns;

use Gcego\Media\Access\MediaAccess;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Support\Bytes;
use Gcego\Media\Support\Keys;
use Gcego\Tables\Columns\Column;
use Gcego\Tables\TablesConfig;

/**
 * A gcego/tables summary of a collection's files: the first file's name,
 * type and size, and how many more there are, with a download link
 * (authorized when followed):
 *
 *     new MediaFile('attachments', 'Attachments')
 */
class MediaFile extends Column
{
    public function cellView(): string
    {
        return 'gcego-media::columns.file';
    }

    public function cellData(mixed $value, mixed $record, TablesConfig $config): array
    {
        $files = Keys::mediaOf($record, $this->columnName());
        $first = $files->first();

        return [
            'count' => $files->count(),
            'name' => $first instanceof MediaRecord ? $first->original_name : null,
            'meta' => $first instanceof MediaRecord ? strtoupper($first->extension).' · '.Bytes::format($first->size) : null,
            'url' => $first instanceof MediaRecord && app(MediaAccess::class)->available($first) ? route('gcego.media.download', ['media' => $first->ulid], false) : null,
            'text' => $first instanceof MediaRecord ? $first->original_name : null,
        ];
    }

    public function exportValue(mixed $value, mixed $record, TablesConfig $config): string|int|float|null
    {
        $files = Keys::mediaOf($record, $this->columnName());

        return $files->map(static fn (MediaRecord $media): string => $media->original_name)->implode(', ');
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return ['gcegoMedia'];
    }

    public function isRelationPath(): bool
    {
        return false;
    }

    public function isSearchable(): bool
    {
        return false;
    }

    public function isSortable(): bool
    {
        return false;
    }
}
