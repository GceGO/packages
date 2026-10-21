<?php

declare(strict_types=1);

namespace Gcego\Tables\Rendering;

use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Tables\Actions\TableActions;
use Gcego\Tables\Query\ColumnAccess;
use Gcego\Tables\Schema\TableSchema;
use Gcego\Tables\Selection\Selection;
use Gcego\Tables\Support\ValueResolver;
use Gcego\Tables\TablesConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns one page of records into view rows: a stable key, the formatted
 * cells of the visible columns (masked columns carry no value at all) and
 * the row actions offered for that record. Records and models never reach
 * Livewire state, only these view rows reach the template.
 */
final readonly class TableRows
{
    public function __construct(private TablesConfig $config, private TableActions $actions) {}

    /**
     * @param  iterable<mixed>  $records
     * @param  list<string>  $columns  Visible columns, in order.
     * @param  list<ResolvedAction>  $rowActions
     * @return list<array{key: string|null, cells: array<string, array<string, mixed>>, actions: list<array{name: string, label: string, icon: string|null, destructive: bool}>, selected: bool}>
     */
    public function build(iterable $records, TableSchema $schema, ColumnAccess $access, array $columns, array $rowActions, Selection $selection, ?string $keyName): array
    {
        $rows = [];

        foreach ($records as $record) {
            $key = self::key($record, $keyName);
            $cells = [];

            foreach ($columns as $name) {
                $column = $schema->columns[$name];

                if ($access->isMasked($name)) {
                    $cells[$name] = ['masked' => true];

                    continue;
                }

                $value = $column->value($record);
                $cells[$name] = [...$column->forRow($value, $record)->cellData($value, $record, $this->config), 'masked' => false];
            }

            $rows[] = [
                'key' => $key,
                'cells' => $cells,
                'actions' => $key === null ? [] : $this->actions->forRecord($rowActions, $record),
                'selected' => $key !== null && $selection->has($key),
            ];
        }

        return $rows;
    }

    public static function key(mixed $record, ?string $keyName): ?string
    {
        $key = $record instanceof Model ? $record->getKey() : ValueResolver::get($record, $keyName ?? 'id');

        return is_int($key) || (is_string($key) && $key !== '' && strlen($key) <= 191) ? (string) $key : null;
    }
}
