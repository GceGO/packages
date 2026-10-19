<?php

declare(strict_types=1);

namespace Gcego\Forms\Schema;

use Gcego\Forms\Configuration\DisabledWhen;
use Gcego\Forms\Configuration\RequiredWhen;
use Gcego\Forms\Configuration\VisibleWhen;
use Gcego\Forms\Fields\Field;
use Gcego\Forms\Fields\Repeater;

/**
 * One field of a compiled schema.
 *
 * $path is a template: "name" at the top level, "objectives.*.title" inside a
 * repeater ("*" stands for an item key). $visibility holds the VisibleWhen
 * conditions of the field and every enclosing layout and repeater, each with
 * the repeater template it is evaluated in.
 */
final readonly class FieldEntry
{
    /**
     * @param  list<array{condition: VisibleWhen, scope: string|null}>  $visibility
     * @param  list<RequiredWhen>  $required
     * @param  list<DisabledWhen>  $disabled
     * @param  list<string>  $siblings  Field names in the same repeater item (or top level).
     */
    public function __construct(
        public Field $field,
        public string $path,
        public string $componentPath,
        public ?string $repeater,
        public array $visibility,
        public array $required,
        public array $disabled,
        public array $siblings,
        public ?int $step,
    ) {}

    public function name(): string
    {
        return $this->field->fieldName();
    }

    public function isRepeater(): bool
    {
        return $this->field instanceof Repeater;
    }

    /**
     * The concrete path for repeater item keys, outermost first: "objectives.i1a2b3c4d5e.title".
     *
     * @param  list<string>  $itemKeys
     */
    public function concrete(array $itemKeys): string
    {
        $segments = explode('.', $this->path);

        foreach ($segments as $index => $segment) {
            if ($segment === '*') {
                $segments[$index] = array_shift($itemKeys) ?? '*';
            }
        }

        return implode('.', $segments);
    }
}
