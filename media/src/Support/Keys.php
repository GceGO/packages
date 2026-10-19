<?php

declare(strict_types=1);

namespace Gcego\Media\Support;

use Gcego\Media\Models\MediaRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Typed access to model keys and to a record's media (HasMedia).
 */
final class Keys
{
    public static function string(mixed $key): string
    {
        return is_scalar($key) ? (string) $key : '';
    }

    public static function int(mixed $key): int
    {
        return is_numeric($key) ? (int) $key : 0;
    }

    /**
     * A record's media in a collection (eager-loaded when the HasMedia relation is loaded).
     *
     * @return Collection<int, MediaRecord>
     */
    public static function mediaOf(mixed $record, string $collection): Collection
    {
        if (! $record instanceof Model || ! method_exists($record, 'mediaIn')) {
            return new Collection;
        }

        $media = $record->mediaIn($collection);

        return $media instanceof Collection ? $media->filter(static fn (mixed $item): bool => $item instanceof MediaRecord)->values() : new Collection;
    }
}
