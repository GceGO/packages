<?php

declare(strict_types=1);

namespace Gcego\Actions\Bulk;

use Gcego\Actions\ActionsConfig;
use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Data\DataManager;
use Gcego\Data\Pagination\Paginate;
use Gcego\Data\Query\Query;
use Gcego\Data\Query\QueryContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Turns an untrusted list of selected keys into records, chunk by chunk,
 * through the blueprint's scoped source: tenant and security scopes, the
 * source's constraints and its listing authorization all apply. Keys of other
 * institutions, missing or malformed keys simply resolve to nothing.
 */
final readonly class SelectionResolver
{
    public function __construct(private DataManager $data, private ActionsConfig $config) {}

    /**
     * @param  array<array-key, mixed>  $keys
     * @return list<string|int>
     *
     * @throws InvalidArgumentException
     */
    public function validate(array $keys): array
    {
        if ($keys === [] || ! array_is_list($keys)) {
            throw new InvalidArgumentException('Select at least one record.');
        }

        if (count($keys) > $this->config->maxSelection) {
            throw new InvalidArgumentException(sprintf('Select at most %d records.', $this->config->maxSelection));
        }

        $valid = [];

        foreach ($keys as $key) {
            if (! (is_int($key) || (is_string($key) && $key !== '' && strlen($key) <= 255 && preg_match('/[\x00-\x1F\x7F]/', $key) !== 1))) {
                throw new InvalidArgumentException('The selection contains an invalid key.');
            }

            $valid[(string) $key] = $key;
        }

        return array_values($valid);
    }

    /**
     * @param  list<string|int>  $keys
     * @return iterable<list<string|int>>
     */
    public function chunks(array $keys): iterable
    {
        yield from array_chunk($keys, max(1, $this->config->chunkSize));
    }

    /**
     * Records of one chunk that exist within the source's scope, keyed by their key as a string.
     *
     * @param  list<string|int>  $chunk
     * @return array<string, Model>
     */
    public function resolve(ResolvedAction $action, array $chunk, QueryContext $context): array
    {
        $source = $action->recordSource([
            new Query(static fn (Builder $query) => $query->whereKey($chunk)),
            Paginate::simple(count($chunk), max: count($chunk)),   // no COUNT query: totals are not needed
        ]);

        if ($source === null) {
            return [];
        }

        $records = [];

        foreach ($this->data->query($source, ['per_page' => count($chunk)], $context)->items() as $record) {
            if ($record instanceof Model && (is_int($record->getKey()) || is_string($record->getKey()))) {
                $records[(string) $record->getKey()] = $record;
            }
        }

        return $records;
    }
}
