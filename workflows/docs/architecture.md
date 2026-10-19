# Architecture

## Layers

- **Definitions** (`Workflow`, `State`, `Transition`, `Branch`, `Approval`, `Stage`, `Task`, `Deadline`, `Automation`):
  - immutable gcego/components;
  - `WorkflowNode` maps named constructor arguments to `Property` values, because gcego/components itself refuses named arguments;
  - `Workflow::problems()` adds the structural rules to component validation;
  - the registry validates each definition when it registers it. Construction does no I/O.
- **Registry:**
  - `WorkflowKind` (kind `workflow`) is registered with `DefinitionKinds` in `register()`;
  - the discovery root is added just before `RegistryConfig` is first resolved;
  - `WorkflowDefinitions` resolves names and decides version compatibility (`forInstance`).
- **Persistence:**
  - `WorkflowInstance`: one row per run, with `lock_version`, and `running` (1 or null) under a unique index;
  - `WorkflowTask`: approval or task work;
  - `WorkflowDecision`: unique per actor, approval, stage and round;
  - `WorkflowEvent`: append-only;
  - `WorkflowCommand`: idempotency records.
- **Engine:** see the call flow below.
- **Side effects:**
  - `Effects` collects events and callbacks;
  - `AutomationDispatcher` queues `ExecuteWorkflowAutomation`, which `AutomationRunner` executes;
  - `WorkflowNotifier` sends through gcego/notifications.
- **Read side:**
  - `WorkflowRepository`: viewer-checked lookups and history;
  - `Inbox`: viewer-checked work lists;
  - `WorkflowLabels`: display labels.
- **UI and integrations:** Livewire components, the panel extension, columns, entries, widgets, the badge and action handlers.

## Call flow of a transition

```
WorkflowEngine::transition
 └ CommandRunner::run                    replay idempotent result? tenant scope
    └ DB transaction
       ├ SELECT … FOR UPDATE              lock the instance row
       ├ expected version check           409 on stale screens
       ├ WorkflowDefinitions::forInstance version compatibility
       ├ TransitionExecutor::apply
       │   ├ check(): status, state, WorkflowAuthorizer, required approval, comment, guard
       │   ├ Branch conditions
       │   ├ HistoryRecorder               workflow.transitioned
       │   └ StateEntry::enter             cancel old work, tasks, approvals, deadline, automation (scheduled)
       ├ compare-and-swap UPDATE … WHERE lock_version = ?   409 if another writer won
       └ WorkflowCommand row              when an idempotency key was given
    └ afterCommit: Laravel events, queued jobs, notifications
```

Approval decisions, task completion, deadline runs and automation follow-ups use the same runner. They run outcome transitions inside the same transaction, as system actors.

## Approvals

`ApprovalCoordinator::open()` starts a round (the highest round for that approval, plus one) and opens stage 0:

- with a reviewers resolver: one task per eligible reviewer;
- otherwise: one pool task.

Each decision is inserted under a unique index and the stage is then resolved:

- **reject:** the approval is rejected;
- **changes:** the approval is returned;
- **approvals reach the policy:** the stage passes, then the next stage opens or the approval completes;
- **the remaining reviewers can no longer reach the policy** (assigned stages): the approval is rejected.

Live reviewers are the stage's open, working or completed tasks. Delegated, escalated and reassigned rows were replaced and don't count.

`outcome()` reads the latest `approval.completed` event after the current state's entry sequence (`state_sequence`), which scopes it to this visit.

## Deadlines and automation

- **Deadlines:** `deadline_at` is set when a state with a `Deadline` is entered. The processor locks each due instance, clears `deadline_at`, records `deadline.reached`, escalates, and runs `then` when it is allowed. Tasks are flagged with `overdue_at` exactly once.
- **Automation jobs** carry identifiers only: instance id, automation name, origin and state sequence.
  - The runner looks the handler up in the definition again.
  - The handler runs outside the workflow transaction, with gcego/security `actingAs(null, tenant)`.
  - A follow-up transition is applied through the runner.
  - An idempotency key per scheduling makes duplicate deliveries no-ops.

## Extending

| To customize | Use |
| --- | --- |
| Guards and conditions | `TransitionGuard` and `Condition` (container-resolved; inside the transaction) |
| Assignment | `AssignmentResolver` (results are filtered by tenant and requirements) |
| Automation | `WorkflowHandler` (idempotent; after commit) |
| UI | publish the views (`gcego-workflows-views`), or override the styling slots on `Workflow::class` |
