<?php

declare(strict_types=1);

use Gcego\Actions\Action;
use Gcego\Actions\ActionManager;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Configuration\Lifecycle;
use Gcego\Actions\Configuration\Transaction;
use Gcego\Actions\Contracts\InputResolver;
use Gcego\Actions\Execution\ActionCompleted;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Actions\Execution\ValidatedInput;
use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Actions\Tests\Fixtures\Models\Subject;
use Gcego\Actions\Tests\Fixtures\Recorder;
use Gcego\Blueprints\Blueprint;
use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Registry\Registry;
use Gcego\Security\Requirements\Ability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->world = world();
    Recorder::$calls = [];
});

function register(Action ...$actions): void
{
    app(Registry::class)->register(new Blueprint('lab', new Ability('viewAny', Subject::class), new Source(Subject::class), ...$actions));
}

function labAction(string $name, Closure $handler, mixed ...$nodes): Action
{
    return new Action($name, new Property(Action::Ability, 'update'), new Property(Action::Record, true), new Handler($handler), ...$nodes);
}

it('commits successful handlers', function (): void {
    register(labAction('touch', fn (ActionContext $context) => $context->record->update(['name' => 'Touched'])));
    actAs($this->world['editor']);

    expect(act('touch', ['record' => $this->world['subjects']['algebra']->id], 'blueprint:lab')->successful())->toBeTrue()
        ->and($this->world['subjects']['algebra']->refresh()->name)->toBe('Touched');
});

it('rolls back every write when the handler fails, and rethrows nothing to the caller', function (): void {
    register(labAction('half', function (ActionContext $context): void {
        $context->record->update(['name' => 'Half written']);
        Subject::query()->whereKey($this->world['subjects']['biology']->id)->update(['name' => 'Also written']);

        throw new RuntimeException('fails after writing');
    }));
    actAs($this->world['editor']);

    $result = act('half', ['record' => $this->world['subjects']['algebra']->id], 'blueprint:lab');

    expect($result->status)->toBe(ActionStatus::Failed)
        ->and($this->world['subjects']['algebra']->refresh()->name)->toBe('Algebra')
        ->and($this->world['subjects']['biology']->refresh()->name)->toBe('Biology');
});

it('nests inside an outer transaction and defers after-commit effects to the outer commit', function (): void {
    register(labAction('effect', function (ActionContext $context): void {
        Recorder::hit('level '.DB::transactionLevel());
        $context->effects->afterCommit(fn () => Recorder::hit('after commit'));
    }));
    actAs($this->world['editor']);

    DB::transaction(function (): void {
        act('effect', ['record' => $this->world['subjects']['algebra']->id], 'blueprint:lab');
        Recorder::hit('outer still open');
    });

    expect(Recorder::$calls)->toBe(['level 2', 'outer still open', 'after commit']);
});

it('never runs after-commit effects after a rollback', function (): void {
    register(labAction('doomed', function (ActionContext $context): void {
        $context->effects->afterCommit(fn () => Recorder::hit('sent email'));

        throw new RuntimeException('rollback');
    }));
    actAs($this->world['editor']);

    act('doomed', ['record' => $this->world['subjects']['algebra']->id], 'blueprint:lab');

    expect(Recorder::$calls)->toBe([]);
});

it('uses the configured connection and can run without a transaction', function (): void {
    register(
        labAction('archived', fn () => Recorder::hit('archive level '.DB::connection('archive')->transactionLevel().', default level '.DB::transactionLevel()), new Transaction(connection: 'archive')),
        labAction('plain', fn () => Recorder::hit('plain level '.DB::transactionLevel()), new Transaction(enabled: false)),
    );
    actAs($this->world['editor']);

    act('archived', ['record' => $this->world['subjects']['algebra']->id], 'blueprint:lab');
    act('plain', ['record' => $this->world['subjects']['algebra']->id], 'blueprint:lab');

    expect(Recorder::$calls)->toBe(['archive level 1, default level 0', 'plain level 0']);
});

it('runs lifecycle hooks in order, never before authorization with the record', function (): void {
    register(labAction('hooked', fn () => Recorder::hit('handler'), new Lifecycle(
        prepare: fn ($resolved) => Recorder::hit('prepare:'.$resolved->name()),
        beforeExecute: fn (ActionContext $context) => Recorder::hit('beforeExecute:'.DB::transactionLevel()),
        afterSuccess: fn () => Recorder::hit('afterSuccess:'.DB::transactionLevel()),
        afterFailure: fn () => Recorder::hit('afterFailure'),
        afterComplete: fn ($context, $result) => Recorder::hit('afterComplete:'.$result->status->value),
    )));
    actAs($this->world['editor']);
    act('hooked', ['record' => $this->world['subjects']['algebra']->id], 'blueprint:lab');

    expect(Recorder::$calls)->toBe(['prepare:hooked', 'beforeExecute:1', 'handler', 'afterSuccess:0', 'afterComplete:success']);

    Recorder::$calls = [];
    actAs($this->world['reader']);
    act('hooked', ['record' => $this->world['subjects']['algebra']->id], 'blueprint:lab');

    expect(Recorder::$calls)->toBe(['prepare:hooked', 'afterFailure', 'afterComplete:denied']);
});

it('reports failing post-commit hooks without changing the result', function (): void {
    register(labAction('noisy', fn () => true, new Lifecycle(afterSuccess: fn () => throw new RuntimeException('hook failed'))));
    actAs($this->world['editor']);

    expect(act('noisy', ['record' => $this->world['subjects']['algebra']->id], 'blueprint:lab')->successful())->toBeTrue();
});

it('dispatches ActionCompleted with the safe result after commit', function (): void {
    Event::fake([ActionCompleted::class]);
    actAs($this->world['editor']);

    act('edit', ['record' => $this->world['subjects']['algebra']->id, 'input' => ['name' => 'X']]);
    act('delete', ['record' => $this->world['subjects']['algebra']->id]);

    // The successful edit and the denied delete (the editor may not delete) both complete.
    Event::assertDispatched(ActionCompleted::class, fn (ActionCompleted $event): bool => $event->result->successful() && $event->userKey === $this->world['editor']->id);
    Event::assertDispatched(ActionCompleted::class, fn (ActionCompleted $event): bool => $event->result->status === ActionStatus::Denied);
    Event::assertDispatchedTimes(ActionCompleted::class, 2);
});

it('treats a handler returning false as a failure', function (): void {
    register(labAction('refuse', fn () => false));
    actAs($this->world['editor']);

    expect(act('refuse', ['record' => $this->world['subjects']['algebra']->id], 'blueprint:lab')->reason)->toBe('handler_returned_false');
});

it('rejects duplicate idempotency keys and releases them after failures', function (): void {
    $calls = 0;
    register(
        labAction('once', function () use (&$calls): void {
            $calls++;
        }, new Property(Action::Idempotent, true)),
        labAction('flaky', function () use (&$calls): void {
            $calls++;

            throw new RuntimeException('try again');
        }, new Property(Action::Idempotent, true)),
    );
    actAs($this->world['editor']);
    $record = $this->world['subjects']['algebra']->id;

    expect(act('once', ['record' => $record], 'blueprint:lab')->reason)->toBe('idempotency_required')
        ->and(act('once', ['record' => $record, 'idempotencyKey' => 'submit-0001'], 'blueprint:lab')->successful())->toBeTrue()
        ->and(act('once', ['record' => $record, 'idempotencyKey' => 'submit-0001'], 'blueprint:lab')->reason)->toBe('duplicate')
        ->and(act('flaky', ['record' => $record, 'idempotencyKey' => 'submit-0002'], 'blueprint:lab')->status)->toBe(ActionStatus::Failed)
        ->and(act('flaky', ['record' => $record, 'idempotencyKey' => 'submit-0002'], 'blueprint:lab')->status)->toBe(ActionStatus::Failed)
        ->and($calls)->toBe(3);
});

it('runs input after-save callbacks with the saved record, inside the transaction', function (): void {
    $world = world();
    actAs($world['admin']);
    $resolver = new class implements InputResolver
    {
        /** @var list<mixed> */
        public array $saved = [];

        public function supports(ResolvedAction $action): bool
        {
            return $action->identity() === 'blueprint:subjects/action:create';
        }

        public function resolve(ResolvedAction $action, ActionContext $context, array $submitted): ValidatedInput
        {
            return new ValidatedInput(['name' => $submitted['name']], [function (Model $record) use ($submitted): void {
                $this->saved[] = $record->getKey();

                if (($submitted['fail'] ?? false) === true) {
                    throw new RuntimeException('Attachment storage failed.');
                }
            }]);
        }

        public function initial(ResolvedAction $action, ActionContext $context): array
        {
            return [];
        }
    };
    app(ActionManager::class)->inputResolver($resolver);

    $ok = act('create', ['input' => ['name' => 'With media']]);
    $failed = act('create', ['input' => ['name' => 'Rolled back', 'fail' => true]]);

    expect($ok->status)->toBe(ActionStatus::Success)
        ->and($resolver->saved[0])->toBe($ok->recordKey)
        ->and($failed->status)->toBe(ActionStatus::Failed)
        ->and(Subject::query()->where('name', 'Rolled back')->exists())->toBeFalse()
        ->and(Subject::query()->where('name', 'With media')->exists())->toBeTrue();
});
