<?php

declare(strict_types=1);

namespace Gcego\Infolists\Schema;

use Gcego\Infolists\Entries\Entry;
use Gcego\Infolists\Infolist;

/**
 * A compiled infolist: every entry by its full access path
 * ("name", "objectives.title") and the relation paths entries read.
 */
final readonly class InfolistSchema
{
    /**
     * @param  array<string, Entry>  $entries  full path => entry (repeatable children prefixed by their repeatable)
     * @param  list<string>  $relations  candidate relation paths (validated against the model later)
     */
    public function __construct(public Infolist $infolist, public array $entries, public array $relations) {}

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return array_keys($this->entries);
    }

    public function pathOf(Entry $entry): ?string
    {
        foreach ($this->entries as $path => $candidate) {
            if ($candidate === $entry) {
                return $path;
            }
        }

        return null;
    }
}
