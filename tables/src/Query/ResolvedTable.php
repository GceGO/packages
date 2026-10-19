<?php

declare(strict_types=1);

namespace Gcego\Tables\Query;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Data\Source;
use Gcego\Tables\Schema\TableSchema;
use Gcego\Tables\Table;

/**
 * A table found inside a registered blueprint (never from client input).
 */
final readonly class ResolvedTable
{
    public function __construct(
        public string $definition,
        public BlueprintInterface $blueprint,
        public Table $table,
        public string $path,
        public Source $source,
        public TableSchema $schema,
    ) {}

    /**
     * The storage identity of this table (preferences, saved views): "blueprint:subjects#table".
     */
    public function identity(): string
    {
        return mb_substr($this->definition.'#'.$this->table->tableName(), 0, 191);
    }
}
