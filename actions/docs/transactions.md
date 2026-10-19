# Transactions

`TransactionCoordinator` uses Laravel only:

- **Transaction:** `DB::connection($connection)->transaction($callback, $attempts)`.
  Nested calls become savepoints, and failures roll back and are rethrown into
  the engine.
- **After commit:** `Connection::afterCommit()` callbacks run after the
  **outermost** commit. They run immediately when no transaction is open, and
  never after a rollback.

## Connection

The connection comes from, in order:

1. `new Transaction(connection: ...)`;
2. the Source's `Connection`;
3. the model's connection;
4. the default.

`new Transaction(enabled: false)` or `Property(Action::Transaction, false)`
runs without one (View does).

## Side effects

Put irreversible side effects (mail, HTTP calls, notifications) in
`$context->effects->afterCommit(fn () => ...)`, or in the `afterSuccess` hook,
never directly in the handler. The `ActionCompleted` event of a successful
action is also dispatched after commit.

Model events still fire inside the transaction.
