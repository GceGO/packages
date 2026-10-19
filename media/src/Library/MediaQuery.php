<?php

declare(strict_types=1);

namespace Gcego\Media\Library;

use DateTimeImmutable;
use Gcego\Media\MediaCollection;

/**
 * Library filters from untrusted input: validated, or ignored. Sort keys
 * come from a fixed list; searches are bound parameters.
 */
final readonly class MediaQuery
{
    public const array Sorts = ['created_at', 'original_name', 'size'];

    public const array Kinds = ['image', 'video', 'audio', 'pdf', 'file'];

    public const array Statuses = ['pending', 'processing', 'ready', 'failed', 'quarantined'];

    /**
     * @param  'asc'|'desc'  $direction
     */
    public function __construct(
        public ?string $search = null,
        public ?string $collection = null,
        public ?string $kind = null,
        public ?string $status = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $until = null,
        public ?int $minSize = null,
        public ?int $maxSize = null,
        public string $sort = 'created_at',
        public string $direction = 'desc',
        public bool $mine = false,
    ) {}

    public static function fromInput(mixed $search = null, mixed $collection = null, mixed $kind = null, mixed $status = null, mixed $from = null, mixed $until = null, mixed $sort = null, mixed $direction = null, mixed $minSize = null, mixed $maxSize = null): self
    {
        $date = static function (mixed $value, bool $end): ?DateTimeImmutable {
            if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                return null;
            }

            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            return $date === false ? null : ($end ? $date->setTime(23, 59, 59) : $date);
        };
        $kilobytes = static fn (mixed $value): ?int => is_numeric($value) && (int) $value >= 0 && (int) $value <= 10_485_760 ? (int) $value * 1024 : null;
        $search = is_string($search) ? trim(mb_substr($search, 0, 100)) : '';

        return new self(
            search: $search === '' ? null : $search,
            collection: is_string($collection) && preg_match(MediaCollection::NamePattern, $collection) === 1 ? $collection : null,
            kind: is_string($kind) && in_array($kind, self::Kinds, true) ? $kind : null,
            status: is_string($status) && in_array($status, self::Statuses, true) ? $status : null,
            from: $date($from, false),
            until: $date($until, true),
            minSize: $kilobytes($minSize),
            maxSize: $kilobytes($maxSize),
            sort: is_string($sort) && in_array($sort, self::Sorts, true) ? $sort : 'created_at',
            direction: $direction === 'asc' ? 'asc' : 'desc',
        );
    }
}
