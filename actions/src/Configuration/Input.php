<?php

declare(strict_types=1);

namespace Gcego\Actions\Configuration;

use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Components\Contracts\ConfigurationNode;

/**
 * Minimal validated input for actions without a gcego/forms schema, using
 * Laravel validation rules. The rule keys are the only writable fields:
 *
 *     new Input([
 *         'name' => ['required', 'string', 'max:120'],
 *         'is_published' => ['boolean'],
 *     ], labels: ['is_published' => 'Published'])
 *
 * Submitted keys without a rule are rejected, never silently persisted.
 * Combine with gcego/security FieldRule nodes for per-user write access.
 */
final readonly class Input implements ConfigurationNode
{
    /**
     * @param  array<string, string|list<mixed>>  $rules
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $messages
     */
    public function __construct(
        public array $rules,
        public array $labels = [],
        public array $messages = [],
    ) {
        if ($rules === []) {
            throw InvalidActionException::invalid('Input', 'declare at least one field rule.');
        }

        foreach (array_keys($rules) as $field) {
            if (! is_string($field) || preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $field) !== 1) {
                throw InvalidActionException::invalid('Input', sprintf('"%s" is not a valid field name.', mb_substr((string) $field, 0, 64)));
            }
        }
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return array_keys($this->rules);
    }

    public function label(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * @return array{fields: list<string>}
     */
    public function normalize(): array
    {
        return ['fields' => $this->fields()];
    }
}
