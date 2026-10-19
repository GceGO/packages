<?php

declare(strict_types=1);

namespace Gcego\Data\Search;

use Gcego\Data\Contracts\SearchStrategy;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Support\Columns;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;

/**
 * The default search: case-insensitive substring matching with LIKE.
 *
 * Each term must match at least one field. The term is a bound parameter with
 * "%", "_" and the escape character escaped, so wildcards in input match
 * literally. Columns come from the trusted Search definition and are wrapped
 * by the query grammar.
 *
 * Case-insensitivity: PostgreSQL uses ILIKE; MySQL/MariaDB follow the column
 * collation (case-insensitive for the default *_ci collations); SQLite's LIKE
 * is case-insensitive for ASCII. Leading-wildcard LIKE cannot use B-tree
 * indexes: use a SearchStrategy with full-text indexes for large tables.
 */
final class LikeSearch implements SearchStrategy
{
    public const string Escape = '!';

    public function apply(EloquentBuilder|QueryBuilder $query, array $fields, array $terms, SourceDefinition $source): void
    {
        foreach ($terms as $term) {
            $pattern = '%'.self::escape($term).'%';

            $query->where(static function (EloquentBuilder|QueryBuilder $group) use ($fields, $pattern): void {
                foreach ($fields as $field) {
                    Columns::where($group, $field, static fn (EloquentBuilder|QueryBuilder $target, string $column) => self::like($target, $column, $pattern), 'or');
                }
            });
        }
    }

    public static function escape(string $term): string
    {
        return str_replace([self::Escape, '%', '_'], [self::Escape.self::Escape, self::Escape.'%', self::Escape.'_'], $term);
    }

    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    private static function like(EloquentBuilder|QueryBuilder $query, string $column, string $pattern): void
    {
        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;
        $connection = $base->getConnection();
        $operator = $connection instanceof Connection && $connection->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        // whereLike() cannot add an ESCAPE clause, and SQLite has no default escape character.
        // The SQL is not a literal only because the column is: a trusted Search field that
        // passed FieldName::Pattern and is wrapped by the grammar. The term is a binding.
        $sql = sprintf("%s %s ? escape '%s'", $base->getGrammar()->wrap($column), $operator, self::Escape);
        $query->whereRaw(new Expression($sql), [$pattern]); // @phpstan-ignore argument.type
    }
}
