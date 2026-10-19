<?php

declare(strict_types=1);

namespace Gcego\Tables\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Tables\Exceptions\InvalidTableException;

/**
 * The table's search box. Searchable columns enable it on their own; add
 * this node to set its placeholder or debounce:
 *
 *     new Table(new Text('name', new Property(Text::Searchable, true)), new Search(placeholder: 'Find a subject'))
 *
 * Only the columns marked Searchable are searched (gcego/data matches the
 * term with bound LIKE parameters).
 */
final readonly class Search implements ConfigurationNode
{
    public function __construct(public ?string $placeholder = null, public ?int $debounceMs = null)
    {
        if ($debounceMs !== null && ($debounceMs < 0 || $debounceMs > 5000)) {
            throw InvalidTableException::invalid('Search', 'debounceMs must be between 0 and 5000.');
        }
    }

    /**
     * @return array{placeholder: string|null, debounce_ms: int|null}
     */
    public function normalize(): array
    {
        return ['placeholder' => $this->placeholder, 'debounce_ms' => $this->debounceMs];
    }
}
