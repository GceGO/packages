<?php

declare(strict_types=1);

namespace Gcego\Tables\Filters;

use Gcego\Data\DataManager;
use Gcego\Data\Pagination\Paginate;
use Gcego\Data\Relationships\RelationPathValidator;
use Gcego\Data\Sorting\Sort;
use Gcego\Data\Source;
use Gcego\Tables\Exceptions\InvalidTableException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Options of a SelectFilter with a Relationship, read through gcego/data as
 * the current user: the related model's policy, tenant scope and data
 * scopes apply, so users only ever see (and can only filter by) related
 * records they may list. At most 100 options; memoized per request (scoped).
 */
final class RelationshipOptions
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $memo = [];

    public function __construct(private readonly DataManager $data) {}

    /**
     * @param  class-string<Model>  $model
     * @return array<string, string> key => title
     */
    public function for(string $model, string $relationship, string $title): array
    {
        $memo = $model.'|'.$relationship.'|'.$title;

        if (isset($this->memo[$memo])) {
            return $this->memo[$memo];
        }

        $related = RelationPathValidator::assert($model, $model, $relationship);

        if (! (new $model)->{$relationship}() instanceof BelongsTo) {
            throw InvalidTableException::invalid(sprintf('SelectFilter relationship "%s"', $relationship), 'only BelongsTo relationships provide filter options.');
        }

        try {
            $result = $this->data->query(new Source($related, new Sort($title), new Paginate(100, max: 100)));
        } catch (AuthorizationException) {
            return $this->memo[$memo] = [];
        }

        $options = [];

        foreach ($result->items() as $record) {
            if ($record instanceof Model && (is_int($record->getKey()) || is_string($record->getKey()))) {
                $label = $record->getAttribute($title);
                $options[(string) $record->getKey()] = is_scalar($label) ? (string) $label : (string) $record->getKey();
            }
        }

        return $this->memo[$memo] = $options;
    }
}
