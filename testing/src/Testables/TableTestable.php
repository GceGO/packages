<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Tables\Actions\TableActions;
use Gcego\Tables\Columns\Column;
use Gcego\Tables\Exceptions\InvalidTableStateException;
use Gcego\Tables\Query\ResolvedTable;
use Gcego\Tables\Schema\TableSchema;
use Gcego\Tables\Table;
use Gcego\Tables\TableManager;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Support\Reports;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;

/**
 * A gcego/tables table: its declared columns and filters, and the rows it
 * returns for the acting user through TableManager, the path TableHost
 * uses (authorize, column access, strict state normalization, query).
 *
 * Built from a registered definition ("subjects", "blueprint:subjects"),
 * every assertion is available; built from a Table instance, only the
 * definition assertions are, since querying needs a source and identity.
 */
final class TableTestable extends Testable
{
    private ?ResolvedTable $resolved = null;

    public function __construct(Verifier $verifier, private readonly Container $container, private readonly TestEnvironment $environment, private readonly string|Table $table)
    {
        parent::__construct($verifier);
    }

    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $tenant, $guard);

        return $this;
    }

    public function schema(): TableSchema
    {
        return $this->table instanceof Table ? $this->manager()->schema($this->table) : $this->resolved()->schema;
    }

    public function assertHasColumn(string $name): self
    {
        return $this->check($this->column($name) !== null, sprintf('assertHasColumn(%s)', $this->export($name)), sprintf('Expected table column [%s] to be declared.', $name), ['column' => $name], ['columns' => $this->schema()->columnNames()]);
    }

    public function assertMissingColumn(string $name): self
    {
        return $this->check($this->column($name) === null, sprintf('assertMissingColumn(%s)', $this->export($name)), sprintf('Expected table column [%s] not to be declared.', $name), ['column' => 'absent'], ['columns' => $this->schema()->columnNames()]);
    }

    public function assertColumnLabel(string $name, string $label): self
    {
        $actual = $this->column($name)?->label();

        return $this->check($actual === $label, sprintf('assertColumnLabel(%s, %s)', $this->export($name), $this->export($label)), sprintf('Expected table column [%s] to be labelled [%s].', $name, $label), ['label' => $label], ['label' => $actual]);
    }

    public function assertColumnSearchable(string $name, bool $searchable = true): self
    {
        $column = $this->column($name);

        return $this->check($column !== null && $column->isSearchable() === $searchable, sprintf('assertColumnSearchable(%s)', $this->export($name)), sprintf('Expected table column [%s] to be %s.', $name, $searchable ? 'searchable' : 'not searchable'), ['searchable' => $searchable], ['searchable' => $column?->isSearchable(), 'component' => $column === null ? '(missing)' : $column::class], $searchable ? 'Enable searching for the registered column (Column::Searchable).' : null);
    }

    public function assertColumnSortable(string $name, bool $sortable = true): self
    {
        $column = $this->column($name);

        return $this->check($column !== null && $column->isSortable() === $sortable, sprintf('assertColumnSortable(%s)', $this->export($name)), sprintf('Expected table column [%s] to be %s.', $name, $sortable ? 'sortable' : 'not sortable'), ['sortable' => $sortable], ['sortable' => $column?->isSortable(), 'component' => $column === null ? '(missing)' : $column::class], $sortable ? 'Enable sorting for the registered column (Column::Sortable).' : null);
    }

    public function assertColumnHideable(string $name, bool $hideable = true): self
    {
        $column = $this->column($name);

        return $this->check($column !== null && $column->isHideable() === $hideable, sprintf('assertColumnHideable(%s)', $this->export($name)), sprintf('Expected table column [%s] to be %s.', $name, $hideable ? 'hideable' : 'always shown'), ['hideable' => $hideable], ['hideable' => $column?->isHideable()]);
    }

    public function assertColumnVisibleByDefault(string $name, bool $visible = true): self
    {
        $column = $this->column($name);

        return $this->check($column !== null && $column->isVisibleByDefault() === $visible, sprintf('assertColumnVisibleByDefault(%s)', $this->export($name)), sprintf('Expected table column [%s] to be %s by default.', $name, $visible ? 'shown' : 'hidden'), ['visible' => $visible], ['visible' => $column?->isVisibleByDefault()]);
    }

    public function assertHasFilter(string $name): self
    {
        return $this->check($this->schema()->filter($name) !== null, sprintf('assertHasFilter(%s)', $this->export($name)), sprintf('Expected the table to declare the filter [%s].', $name), ['filter' => $name], ['filters' => array_keys($this->schema()->filters)]);
    }

    public function assertSearchable(bool $searchable = true): self
    {
        return $this->check($this->schema()->isSearchable() === $searchable, 'assertSearchable('.$this->export($searchable).')', $searchable ? 'Expected the table to offer search.' : 'Expected the table not to offer search.', ['searchable' => $searchable], ['searchable' => $this->schema()->isSearchable()]);
    }

    public function assertDefaultPerPage(int $perPage): self
    {
        $actual = $this->schema()->pagination->perPage ?? $this->manager()->config()->defaultPerPage;

        return $this->check($actual === $perPage, sprintf('assertDefaultPerPage(%d)', $perPage), sprintf('Expected %d rows per page by default.', $perPage), ['per page' => $perPage], ['per page' => $actual]);
    }

    public function assertHasRowAction(string $action): self
    {
        $names = $this->actionNames(false);

        return $this->check(in_array($action, $names, true), sprintf('assertHasRowAction(%s)', $this->export($action)), sprintf('Expected the row action [%s].', $action), ['action' => $action], ['row actions' => $names]);
    }

    public function assertHasBulkAction(string $action): self
    {
        $names = $this->actionNames(true);

        return $this->check(in_array($action, $names, true), sprintf('assertHasBulkAction(%s)', $this->export($action)), sprintf('Expected the bulk action [%s].', $action), ['action' => $action], ['bulk actions' => $names]);
    }

    /**
     * Runs the table query as the acting user. $state is untrusted table
     * state: search, filters, sorts ([['column' => …, 'direction' => …]]),
     * page, per_page, cursor.
     *
     * @param  array<string, mixed>  $state
     */
    public function query(array $state = []): ResultTestable
    {
        $manager = $this->manager();
        $table = $this->resolved();
        $manager->authorize($table);
        $access = $manager->access($table->schema);
        $normalized = $manager->normalizer($table->schema, $access, model: $manager->model($table))->normalize($state);

        return new ResultTestable($this->verifier, $manager->query($table, $access, $normalized), ['Table' => $table->definition, 'State' => $state]);
    }

    /**
     * The state is refused (forged sort column, undeclared filter, bad
     * page…) before any query runs.
     *
     * @param  array<string, mixed>  $state
     */
    public function assertRejectsState(array $state): self
    {
        $exception = $this->caught(fn () => $this->query($state));

        return $this->check($exception instanceof InvalidTableStateException, 'assertRejectsState('.$this->export($state).')', 'Expected the table state to be refused.', ['exception' => InvalidTableStateException::class], ['exception' => $this->describe($exception)]);
    }

    /**
     * The acting user may not open the table at all.
     */
    public function assertForbidden(): self
    {
        $exception = $this->caught(fn () => $this->manager()->authorize($this->resolved()));

        return $this->check($exception instanceof AuthorizationException, 'assertForbidden()', 'Expected the acting user to be refused the table.', ['exception' => AuthorizationException::class], ['exception' => $this->describe($exception)]);
    }

    /**
     * The column is removed entirely for the acting user (no read access).
     */
    public function assertColumnHidden(string $name): self
    {
        $access = $this->manager()->access($this->schema());

        return $this->check(! $access->canRead($name), sprintf('assertColumnHidden(%s)', $this->export($name)), sprintf('Expected table column [%s] to be unreadable for the acting user.', $name), ['readable' => false], ['readable' => $access->canRead($name), 'masked' => $access->isMasked($name)]);
    }

    public function assertColumnMasked(string $name): self
    {
        $access = $this->manager()->access($this->schema());

        return $this->check($access->isMasked($name), sprintf('assertColumnMasked(%s)', $this->export($name)), sprintf('Expected table column [%s] to be masked for the acting user.', $name), ['masked' => true], ['masked' => $access->isMasked($name), 'readable' => $access->canRead($name)]);
    }

    public function assertColumnReadable(string $name): self
    {
        $access = $this->manager()->access($this->schema());

        return $this->check($access->canRead($name) && ! $access->isMasked($name), sprintf('assertColumnReadable(%s)', $this->export($name)), sprintf('Expected table column [%s] to be readable for the acting user.', $name), ['readable' => true], ['readable' => $access->canRead($name), 'masked' => $access->isMasked($name)]);
    }

    /**
     * The export (as the acting user) has exactly these columns, in order.
     *
     * @param  list<string>  $columns
     * @param  array<string, mixed>  $state
     */
    public function assertExportColumns(array $columns, array $state = []): self
    {
        $actual = $this->manager()->export($this->resolved()->definition, $state)->columns;

        return $this->check($actual === $columns, 'assertExportColumns('.$this->export($columns).')', 'Expected the export to contain exactly these columns.', ['columns' => $columns], ['columns' => $actual]);
    }

    public function assertHealthy(): self
    {
        $problems = Reports::problems($this->manager()->inspect($this->table instanceof Table ? $this->table : $this->resolved()->definition));

        return $this->check($problems === [], 'assertHealthy()', 'Expected the table inspector to report no errors.', ['errors' => []], ['errors' => $problems]);
    }

    protected function subject(): string
    {
        return 'table';
    }

    protected function context(): array
    {
        return ['Table' => is_string($this->table) ? $this->table : $this->table->tableName()];
    }

    private function column(string $name): ?Column
    {
        return $this->schema()->column($name);
    }

    /**
     * @return list<string>
     */
    private function actionNames(bool $bulk): array
    {
        $actions = $this->container->make(TableActions::class);
        $resolved = $bulk ? $actions->bulk($this->resolved()) : $actions->row($this->resolved());

        return array_values(array_map(static fn (ResolvedAction $action): string => $action->name(), $resolved));
    }

    private function resolved(): ResolvedTable
    {
        if ($this->table instanceof Table) {
            throw new \LogicException('This assertion needs a registered definition: use GcegoTest::table("subjects") instead of a Table instance.');
        }

        return $this->resolved ??= $this->manager()->resolve($this->table);
    }

    private function manager(): TableManager
    {
        return $this->container->make(TableManager::class);
    }
}
