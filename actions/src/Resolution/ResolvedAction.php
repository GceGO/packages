<?php

declare(strict_types=1);

namespace Gcego\Actions\Resolution;

use Gcego\Actions\Action;
use Gcego\Actions\ActionsConfig;
use Gcego\Actions\BulkAction;
use Gcego\Actions\Configuration\Confirmation;
use Gcego\Actions\Configuration\Transaction;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Data\Pagination\Paginate;
use Gcego\Data\Query\Query;
use Gcego\Data\Source;
use Gcego\Data\Transform\Transform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * An action as found in its trusted, registered blueprint.
 *
 * identity() is stable: "blueprint:subjects/action:delete". $path is the
 * component path used by gcego/security ("subjects.delete").
 */
final readonly class ResolvedAction
{
    public function __construct(
        public string $definition,
        public BlueprintInterface $blueprint,
        public Action $action,
        public string $path,
        public ?Source $source,
    ) {}

    public static function identityOf(string $definition, string $action): string
    {
        return $definition.'/action:'.$action;
    }

    public function identity(): string
    {
        return self::identityOf($this->definition, $this->name());
    }

    public function name(): string
    {
        return $this->action->actionName();
    }

    public function isBulk(): bool
    {
        return $this->action instanceof BulkAction;
    }

    /**
     * @return class-string<Model>|null
     */
    public function model(): ?string
    {
        $model = $this->source?->get(Source::Model);

        return is_string($model) && is_subclass_of($model, Model::class) ? $model : null;
    }

    public function requiresConfirmation(ActionsConfig $config): bool
    {
        $explicit = $this->action->confirmationSetting();

        if ($explicit !== null) {
            return $explicit;
        }

        if ($this->action->configurations()->has(Confirmation::class)) {
            return true;
        }

        $key = $this->action::confirmationKey();

        return $key !== null && ($config->confirmation[$key] ?? false);
    }

    public function confirmation(): ?Confirmation
    {
        $node = $this->action->configurations()->first(Confirmation::class);

        return $node instanceof Confirmation ? $node : null;
    }

    public function usesTransaction(ActionsConfig $config): bool
    {
        $node = $this->action->configurations()->first(Transaction::class);
        $explicit = $this->action->get(Action::Transaction);

        return match (true) {
            $node instanceof Transaction => $node->enabled,
            is_bool($explicit) => $explicit,
            default => $config->transactions,
        };
    }

    public function transactionConnection(): ?string
    {
        $node = $this->action->configurations()->first(Transaction::class);

        if ($node instanceof Transaction && $node->connection !== null) {
            return $node->connection;
        }

        $connection = $this->source?->get(Source::Connection);
        $model = $this->model();

        return is_string($connection) ? $connection : ($model === null ? null : (new $model)->getConnectionName());
    }

    public function transactionAttempts(): int
    {
        $node = $this->action->configurations()->first(Transaction::class);

        return $node instanceof Transaction ? max(1, $node->attempts) : 1;
    }

    /**
     * The source used to resolve records for this action: the blueprint's
     * source with its constraints and scopes, without output transforms and
     * paging, and with trashed rows only where the action type asks for them.
     *
     * @param  list<ConfigurationNode>  $extra
     */
    public function recordSource(array $extra = []): ?Source
    {
        if ($this->source === null) {
            return null;
        }

        $nodes = array_values(array_filter($this->source->configurations()->all(), static fn (mixed $node): bool => ! $node instanceof Transform && ! $node instanceof Paginate));

        $trashed = $this->action::trashed();

        if ($trashed !== null) {
            // What SoftDeletes' withTrashed()/onlyTrashed() do: lift only the soft-delete scope; every other scope stays.
            $nodes[] = new Query(static function (Builder $query) use ($trashed): void {
                $query->withoutGlobalScope(SoftDeletingScope::class);
                $model = $query->getModel();

                $column = method_exists($model, 'getQualifiedDeletedAtColumn') ? $model->getQualifiedDeletedAtColumn() : null;

                if ($trashed === 'only' && is_string($column)) {
                    $query->whereNotNull($column);
                }
            });
        }

        return $this->source->withoutProperty(Source::Pagination)->withConfigurations(...$nodes, ...$extra);
    }
}
