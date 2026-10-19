<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Actions\Exceptions\InvalidActionException;

/**
 * Where actions appear. Renderers (pages, tables, forms) ask for one placement;
 * the actions package lays nothing out itself.
 *
 * Packages register their own placements: ActionPlacement::register('kanban.card').
 */
final class ActionPlacement
{
    public const string Header = 'header';

    public const string Toolbar = 'toolbar';

    public const string Row = 'row';

    public const string Record = 'record';

    public const string FormFooter = 'form-footer';

    public const string Bulk = 'bulk';

    public const string Context = 'context';

    public const string Pattern = '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/';

    /**
     * @var array<string, true>
     */
    private array $placements = [];

    public function __construct()
    {
        foreach ([self::Header, self::Toolbar, self::Row, self::Record, self::FormFooter, self::Bulk, self::Context] as $placement) {
            $this->placements[$placement] = true;
        }
    }

    public function register(string $placement): void
    {
        if (preg_match(self::Pattern, $placement) !== 1) {
            throw InvalidActionException::invalid('placement "'.mb_substr($placement, 0, 40).'"', 'use lowercase letters, digits, ".", "-" or "_".');
        }

        $this->placements[$placement] = true;
    }

    public function has(string $placement): bool
    {
        return isset($this->placements[$placement]);
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_keys($this->placements);
    }
}
