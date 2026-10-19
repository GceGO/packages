<?php

declare(strict_types=1);

namespace Gcego\Infolists\Resolution;

use Gcego\Infolists\Schema\InfolistSchema;
use Gcego\Security\Fields\FieldAccess;
use Gcego\Security\Fields\FieldRule;
use Gcego\Security\SecurityManager;

/**
 * Entry read decisions for the current user and one record (gcego/security
 * FieldRules from each entry's Access node). Unreadable entries are never
 * resolved; masked ones show a mask.
 */
final readonly class EntryAccess
{
    /**
     * @param  array<string, FieldAccess>  $levels  entry path => access
     */
    public function __construct(private array $levels) {}

    public static function decide(InfolistSchema $schema, mixed $record, SecurityManager $security): self
    {
        $rules = [];

        foreach ($schema->entries as $path => $entry) {
            $rules[] = $entry->access()?->rule($path) ?? new FieldRule($path);
        }

        $decisions = $security->fields($rules, $record);
        $levels = [];

        foreach ($schema->paths() as $path) {
            $levels[$path] = $decisions->access($path);
        }

        return new self($levels);
    }

    public static function all(InfolistSchema $schema): self
    {
        return new self(array_fill_keys($schema->paths(), FieldAccess::Readable));
    }

    public function level(string $path): FieldAccess
    {
        return $this->levels[$path] ?? FieldAccess::Hidden;
    }

    /**
     * Whether a record path may be read (paths that are not entries are not governed here).
     */
    public function canReadPath(string $path): bool
    {
        return ! isset($this->levels[$path]) || $this->levels[$path]->canRead();
    }

    /**
     * @return list<string>
     */
    public function readable(): array
    {
        return array_keys(array_filter($this->levels, static fn (FieldAccess $level): bool => $level->canRead()));
    }
}
