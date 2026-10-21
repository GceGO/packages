<?php

declare(strict_types=1);

namespace Gcego\Infolists\Building;

use Closure;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Infolists\Configuration\Access;
use Gcego\Infolists\Configuration\Format;
use Gcego\Infolists\Configuration\Resolve;
use Gcego\Infolists\Configuration\VisibleWhen;
use Gcego\Infolists\Entries\Entry;
use Gcego\Security\Contracts\Requirement;

/**
 * The fluent form of an infolist entry:
 *
 *     Text::display('address', 'Address')->multiline()->columnSpan(3)
 *     BadgeEntry::define('status', 'Status')->variants([...])->labels(Status::labels())
 *
 * Every entry property is available by name (placeholder(), copyable(),
 * tooltip(), hiddenWhenEmpty(), and so on). Presentation properties also
 * take closures injected with $state, $record (also by model type), $root,
 * $entry and $user:
 *
 *     Text::make('code')->tooltip(fn (Subject $record): string => $record->name)
 *     Text::make('full_name')->state(fn (Teacher $record): string => $record->first_name.' '.$record->last_name)
 */
class EntryBuilder extends ComponentBuilder
{
    /**
     * Compute the value: fn (Teacher $record): string => ...
     */
    public function state(Closure $state): static
    {
        return $this->replaceConfiguration(new Resolve($state, injected: true));
    }

    /**
     * Format the text with injected parameters: fn (?int $state, Subject $record): ?string. Always escaped.
     */
    public function formatStateUsing(Closure $formatter): static
    {
        return $this->replaceConfiguration(new Format($formatter, injected: true));
    }

    /**
     * true/false, or a closure fn (Subject $record): bool evaluated against the authorized record.
     */
    public function visible(bool|Closure $condition = true): static
    {
        return $condition instanceof Closure ? $this->configure(VisibleWhen::computed($condition)) : $this->set(Entry::Hidden, ! $condition);
    }

    public function hidden(bool|Closure $condition = true): static
    {
        return $condition instanceof Closure ? $this->configure(VisibleWhen::computed($condition, negate: true)) : $this->set(Entry::Hidden, $condition);
    }

    /**
     * Who may read the entry; others see nothing, or a mask.
     */
    public function access(?Requirement $read = null, bool $mask = false): static
    {
        return $this->replaceConfiguration(new Access($read, $mask));
    }

    /**
     * Format the text: fn (mixed $value, mixed $record): ?string, or a formatter class.
     */
    public function formatUsing(Closure|string $formatter): static
    {
        return $this->replaceConfiguration(new Format($formatter));
    }

    /**
     * Compute the value from the record instead of reading the entry's path.
     */
    public function resolveUsing(Closure|string $resolver): static
    {
        return $this->replaceConfiguration(new Resolve($resolver));
    }

    /**
     * Show the entry only while another value of the record matches.
     */
    public function visibleWhen(string $path, mixed $value = true, string $operator = 'equals'): static
    {
        return $this->configure(new VisibleWhen($path, $value, $operator));
    }
}
