<?php

declare(strict_types=1);

namespace Gcego\Cli\Generation;

/**
 * One attribute of a model the generators may declare.
 */
final readonly class ModelField
{
    /**
     * @param  string  $type  string, text, boolean, date, datetime, integer, decimal, enum
     * @param  bool  $verified  true when read from the database schema, false when inferred from the model alone
     * @param  class-string|null  $enum
     * @param  string|int|float|bool|null  $default  the column's literal default (null: none, or an expression)
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $verified,
        public bool $required = false,
        public ?int $length = null,
        public ?string $enum = null,
        public bool $fillable = true,
        public string|int|float|bool|null $default = null,
    ) {}
}
