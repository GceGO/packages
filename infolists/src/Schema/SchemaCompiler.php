<?php

declare(strict_types=1);

namespace Gcego\Infolists\Schema;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Infolists\Entries\Entry;
use Gcego\Infolists\Entries\RepeatableEntry;
use Gcego\Infolists\Exceptions\InvalidEntryException;
use Gcego\Infolists\Infolist;
use Gcego\Infolists\Layout\LayoutComponent;

/**
 * Compiles an Infolist (pure, query-free): entry paths, duplicate detection
 * per scope (two entries for the same path in the same infolist or
 * repeatable), and the relation paths to eager-load.
 */
final class SchemaCompiler
{
    /**
     * @var array<string, Entry>
     */
    private array $entries = [];

    /**
     * @var list<string>
     */
    private array $relations = [];

    public function compile(Infolist $infolist): InfolistSchema
    {
        $this->entries = [];
        $this->relations = [];
        $this->walk($infolist, null);

        return new InfolistSchema($infolist, $this->entries, array_values(array_unique($this->relations)));
    }

    private function walk(ComponentInterface $parent, ?string $scope): void
    {
        foreach ($parent->children() as $child) {
            if ($child instanceof Entry) {
                $path = ($scope === null ? '' : $scope.'.').$child->entryName();

                if (isset($this->entries[$path])) {
                    throw InvalidEntryException::invalid(sprintf('Entry "%s"', $path), 'two entries show the same path; remove one (or show it once).');
                }

                if (substr_count($path, '.') > 3) {
                    throw InvalidEntryException::invalid(sprintf('Entry "%s"', $path), 'paths are limited to four segments, repeatable included.');
                }

                $this->entries[$path] = $child;

                foreach ($child->relations() as $relation) {
                    $this->relations[] = ($scope === null ? '' : $scope.'.').$relation;
                }

                if ($child instanceof RepeatableEntry) {
                    $this->walk($child, $path);
                }

                continue;
            }

            if ($child instanceof LayoutComponent) {
                $this->walk($child, $scope);
            }
        }
    }
}
