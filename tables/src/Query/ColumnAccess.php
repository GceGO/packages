<?php

declare(strict_types=1);

namespace Gcego\Tables\Query;

use Gcego\Security\Fields\FieldAccess;
use Gcego\Security\Fields\FieldRule;
use Gcego\Security\SecurityManager;
use Gcego\Tables\Schema\TableSchema;

/**
 * Column read decisions for the current user, through gcego/security
 * FieldRules (one decision per column per request, without a record).
 * Columns whose visible()/hidden() closure removes them for this user are
 * neither readable nor masked, so they are never queried or shown.
 */
final readonly class ColumnAccess
{
    /**
     * @param  list<string>  $readable
     * @param  list<string>  $masked
     */
    public function __construct(public array $readable, public array $masked) {}

    public static function decide(TableSchema $schema, SecurityManager $security): self
    {
        $rules = [];

        foreach ($schema->columns as $name => $column) {
            $rules[] = $column->access()?->rule($name) ?? new FieldRule($name);
        }

        $decisions = $security->fields($rules);
        $user = $security->context()->user;
        $readable = [];
        $masked = [];

        foreach ($schema->columnNames() as $name) {
            if (! $schema->columns[$name]->isPresent(['user' => $user, 'table' => $schema->table])) {
                continue;
            }

            $level = $decisions->access($name);

            if ($level->canRead()) {
                $readable[] = $name;
            } elseif ($level === FieldAccess::Redacted) {
                $masked[] = $name;
            }
        }

        return new self($readable, $masked);
    }

    public static function all(TableSchema $schema): self
    {
        return new self($schema->columnNames(), []);
    }

    public function canRead(string $column): bool
    {
        return in_array($column, $this->readable, true);
    }

    public function isMasked(string $column): bool
    {
        return in_array($column, $this->masked, true);
    }

    public function fingerprint(): string
    {
        return implode(',', $this->readable).'|'.implode(',', $this->masked);
    }
}
