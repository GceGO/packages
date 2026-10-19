<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Data\Results\DataResult;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Support\Records;
use Illuminate\Database\Eloquent\Model;

/**
 * Assertions about a gcego/data result: the rows a source or a table
 * returned for the acting user, in order, with pagination.
 */
final class ResultTestable extends Testable
{
    /**
     * @param  array<string, mixed>  $identity  Lines identifying the query in failure output.
     */
    public function __construct(Verifier $verifier, private readonly DataResult $result, private readonly array $identity = [], private readonly ?string $keyName = null)
    {
        parent::__construct($verifier);
    }

    public function result(): DataResult
    {
        return $this->result;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return Records::keys($this->result->items(), $this->keyName);
    }

    /**
     * Rows on this page.
     */
    public function assertCount(int $count): self
    {
        return $this->check($this->result->count() === $count, sprintf('assertCount(%d)', $count), sprintf('Expected %d row(s) on the page.', $count), ['rows' => $count], ['rows' => $this->result->count(), 'keys' => $this->keys()]);
    }

    /**
     * Rows across all pages (needs a paginator that counts).
     */
    public function assertTotal(int $total): self
    {
        $actual = $this->result->pagination->total;

        return $this->check($actual === $total, sprintf('assertTotal(%d)', $total), sprintf('Expected %d row(s) in total.', $total), ['total' => $total], ['total' => $actual]);
    }

    public function assertEmpty(): self
    {
        return $this->check($this->result->isEmpty(), 'assertEmpty()', 'Expected no rows.', ['rows' => 0], ['keys' => $this->keys()]);
    }

    /**
     * Exactly these keys, in this order.
     *
     * @param  iterable<string|int|Model>  $keys
     */
    public function assertKeys(iterable $keys): self
    {
        $expected = Records::normalize($keys);

        return $this->check($this->keys() === $expected, 'assertKeys('.$this->export($expected).')', 'Expected exactly these rows, in order.', ['keys' => $expected], ['keys' => $this->keys()]);
    }

    /**
     * @param  iterable<string|int|Model>  $keys
     */
    public function assertContains(iterable $keys): self
    {
        $missing = array_values(array_diff(Records::normalize($keys), $this->keys()));

        return $this->check($missing === [], 'assertContains('.$this->export(Records::normalize($keys)).')', 'Expected the rows to include these keys.', ['includes' => Records::normalize($keys)], ['keys' => $this->keys(), 'missing' => $missing]);
    }

    /**
     * None of these keys is returned: the cross-tenant and unauthorized
     * record check.
     *
     * @param  iterable<string|int|Model>  $keys
     */
    public function assertMissing(iterable $keys): self
    {
        $leaked = array_values(array_intersect(Records::normalize($keys), $this->keys()));

        return $this->check($leaked === [], 'assertMissing('.$this->export(Records::normalize($keys)).')', 'Expected the rows not to include these keys.', ['excludes' => Records::normalize($keys)], ['leaked' => $leaked]);
    }

    /**
     * The values of a field, in row order.
     *
     * @param  list<mixed>  $values
     */
    public function assertPluck(string $field, array $values): self
    {
        $actual = Records::pluck($this->result->items(), $field);

        return $this->check($actual === $values, sprintf('assertPluck(%s, %s)', $this->export($field), $this->export($values)), sprintf('Expected the [%s] values in this order.', $field), [$field => $values], [$field => $actual]);
    }

    public function assertPage(int $page, ?int $perPage = null): self
    {
        $pagination = $this->result->pagination;

        return $this->check($pagination->currentPage === $page && ($perPage === null || $pagination->perPage === $perPage), sprintf('assertPage(%d, %s)', $page, $this->export($perPage)), sprintf('Expected page %d.', $page), ['page' => $page, 'per page' => $perPage], ['page' => $pagination->currentPage, 'per page' => $pagination->perPage]);
    }

    public function assertHasMorePages(bool $more = true): self
    {
        return $this->check($this->result->pagination->hasMorePages === $more, 'assertHasMorePages('.$this->export($more).')', $more ? 'Expected more pages.' : 'Expected the last page.', ['more pages' => $more], ['more pages' => $this->result->pagination->hasMorePages]);
    }

    protected function subject(): string
    {
        return 'data';
    }

    protected function context(): array
    {
        return [...$this->identity, 'Source' => $this->result->source];
    }
}
