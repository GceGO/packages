# gcego/workflows

Declarative business workflows for GCEGO Admin Studio. You declare states, guarded and conditional transitions, approvals, human tasks, deadlines and automation in PHP. The package runs them as persistent instances:

- authorized on the server (deny by default) and tenant-scoped;
- protected against concurrent writers and duplicate submissions;
- recorded in an append-only history;
- with side effects (automation, notifications, events) released only after the database transaction commits.

The package builds on the GCEGO layers instead of duplicating them:

- definitions are gcego/morfaw and are discovered by gcego/registry (kind `workflow`);
- authorization uses Laravel policies and gates plus gcego/security requirements and tenancy;
- automation and deadlines use Laravel's queue and scheduler;
- notifications go through gcego/notifications;
- the UI is Livewire and Tailwind (gcego/chroma) inside gcego/panels;
- integrations cover gcego/actions, tables, infolists, widgets and navigation.

## Contents

- [Requirements and installation](#requirements-and-installation)
- [Declaring a workflow](#declaring-a-workflow)
- [Running workflows](#running-workflows)
- [States and transitions](#states-and-transitions)
- [Approvals](#approvals)
- [Human tasks, assignment and delegation](#human-tasks-assignment-and-delegation)
- [Deadlines and escalations](#deadlines-and-escalations)
- [Automation](#automation)
- [Authorization, system actors and tenancy](#authorization-system-actors-and-tenancy)
- [Concurrency, idempotency and versioning](#concurrency-idempotency-and-versioning)
- [History and observability](#history-and-observability)
- [User interface](#user-interface)
- [Integrations](#integrations)
- [Configuration](#configuration)
- [Accessibility](#accessibility)
- [Testing and troubleshooting](#testing-and-troubleshooting)

Further reading:

- [docs/architecture.md](docs/architecture.md)
- [docs/security.md](docs/security.md)
- [docs/api-reference.md](docs/api-reference.md)

## Requirements and installation

- PHP 8.3+ and Laravel 13.
- Livewire 4.
- The GCEGO packages listed in `composer.json` (core through notifications).

```bash
composer require gcego/workflows
php artisan migrate
php artisan vendor:publish --tag=gcego-workflows-config   # optional
```

The migration creates five tables:

- `gcego_workflow_instances`
- `gcego_workflow_tasks`
- `gcego_workflow_decisions`
- `gcego_workflow_events` (the append-only history)
- `gcego_workflow_commands` (idempotency)

Morph key columns are strings, so subjects and people may have integer, ULID or UUID keys.

Run a queue worker for the `workflows` queue so queued automation runs. Laravel's scheduler (`php artisan schedule:work` or cron) processes deadlines every five minutes.

## Declaring a workflow

Put each workflow in `app/GCEGo/Workflows/<name>.php`, returning a `Workflow`. Constructing it never queries or executes anything: it is validated when the registry loads it, and invalid workflows are refused with every problem listed.

<!-- example:workflow -->
```php
use Gcego\Security\Requirements\Ability;
use Gcego\Workflows\Approvals\Approval;
use Gcego\Workflows\Scheduling\Deadline;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;

return new Workflow(
    new State('draft', 'Draft', color: 'neutral'),
    new State('submitted', 'Submitted', new Deadline('14 days', then: 'expire')),
    new State('under-review', 'Under review', color: 'warning'),
    new State('returned', 'Returned for changes', color: 'warning'),
    new State('approved', 'Approved', terminal: true),
    new State('rejected', 'Rejected', terminal: true, color: 'danger'),
    new State('expired', 'Expired', terminal: true, color: 'neutral'),

    new Transition('submit', ['draft', 'returned'], 'submitted'),
    new Transition('review', 'submitted', 'under-review', ability: 'review'),
    new Transition('approve', 'under-review', 'approved', human: false),
    new Transition('reject', 'under-review', 'rejected', human: false),
    new Transition('return', 'under-review', 'returned', human: false),
    new Transition('expire', 'submitted', 'expired', human: false, systemActors: ['deadlines']),

    new Approval('academic-review', 'Academic review',
        new Ability('applications.review'),
        state: 'under-review',
        policy: 'quorum',
        requiredApprovals: 2,
        onApprove: 'approve',
        onReject: 'reject',
        onChanges: 'return',
        due: '3 days',
    ),

    name: 'institution-registration',
    title: 'Institution Registration',
    version: 1,
);
```

How the arguments work:

- **Named or positional:** every part accepts named arguments (`terminal: true`) or the equivalent `new Property(State::Terminal, true)`. Both produce the same definition.
- **Order:** PHP requires positional arguments (children, requirements) before named ones.
- **Unknown names:** an unknown named argument is an error, so a typo such as `colour:` is caught at once.
- **The initial state** is the first state unless `initial:` names another.

## Running workflows

`WorkflowEngine` is the only way to change a workflow. You always pass the actor from server code: the authenticated user, or a `SystemActor`.

<!-- example:engine -->
```php
use Gcego\Workflows\Approvals\Decision;
use Gcego\Workflows\Engine\WorkflowEngine;

$engine = app(WorkflowEngine::class);

$instance = $engine->start('institution-registration', $application, $editor);
$engine->transition($instance, 'submit', $editor, expectedVersion: $instance->lock_version);
$engine->transition($instance, 'review', $reviewer);

// A pool approval: anyone with "applications.review" may decide, once.
$task = $instance->tasks()->open()->firstOrFail();

foreach ([$firstReviewer, $secondReviewer] as $person) {
    $engine->decide($task, Decision::Approve, $person, 'Accreditation verified.');
}

return $instance->refresh()->state;   // "approved": the quorum approved and ran "approve"
```

Each command returns a `TransitionResult` (instance, transition, from, to, status, version, correlation).

| Command | Purpose |
| --- | --- |
| `start($workflow, $subject, $actor, idempotencyKey:, tenant:)` | Start a workflow. `tenant:` is for system actors only. |
| `transition($instance, $name, $actor, $comment, expectedVersion:, idempotencyKey:, input:)` | Run a transition. |
| `decide($task, Decision, $user, $comment)` | Record an approval decision. |
| `claim($task, $user)` | Take a pool task. |
| `completeTask($task, $user, $comment)` | Complete a task. |
| `delegate($task, $user, $to, $comment)` | Pass your own task to someone eligible. |
| `reassign($task, $manager, $to)` | Move an open task as a manager. |
| `suspend()` / `resume()` / `cancel($instance, $actor, $comment)` | Manage the instance's lifecycle. |
| `available($instance, $user)` | Transitions the user may run, each enabled, or disabled with a reason. |
| `running($workflow, $subject)` | The subject's running instance. |
| `canWorkOn($task, $user)` | Whether the user may act on a task. |

Refusals are typed exceptions:

- `InvalidTransitionException`: a stable `reason` code plus a translated message;
- `WorkflowAuthorizationException`: 403;
- `WorkflowConflictException`: 409, for stale screens, concurrent writers, or an already running instance;
- `WorkflowNotFoundException`: 404, including other tenants' instances;
- `WorkflowDefinitionException`: invalid definitions or incompatible versions.

## States and transitions

**States** can carry a label, `color` (neutral, info, success, warning or danger; always shown together with the label), `icon`, `terminal` and scalar `metadata`. Children add behaviour on entering:

- `Task`: human work;
- `Deadline`: what happens when the state lasts too long;
- `Automation`: trusted handlers.

Entering a terminal state completes the instance.

**Transitions** may leave one state, a list of states, or `'*'`. A transition runs in this order:

1. The instance must be active. Suspended and cancelled instances refuse; a completed one accepts only transitions marked `reopen: true`.
2. The transition must leave the instance's current state. That state is read from the database, never from the browser.
3. The actor must be authorized: the subject policy's `ability` (default `update`) and any gcego/security requirements passed as children.
4. `requiresApproval:` must be approved during the current visit of the state.
5. The comment rule must hold: `comment: 'none' | 'optional' | 'required'`.
6. The `guard:` class (a `TransitionGuard`) must allow it. It may deny with a reason, which the UI shows.
7. The first `Branch` child whose `Condition` passes picks the destination; otherwise `to` applies.
8. The history is appended and the destination is entered: open tasks of the old state close, and the new state's tasks, approvals, deadline and automation start.

`confirmation: true` asks the UI to confirm. `human: false` makes a transition system-only.

## Approvals

An `Approval` opens each time its `state` is entered, starting a new round; decisions from earlier rounds never count.

**Reviewers:**

- an `AssignmentResolver` (`reviewers:`) gives one task per eligible person;
- without one, the step is a **pool**: anyone satisfying the step's requirements may decide.

**Policies:**

| Policy | Approves when |
| --- | --- |
| `any` | one approval |
| `all` | every assigned reviewer (pool: `requiredApprovals`) |
| `quorum` | `requiredApprovals` approvals |
| `majority` | more than half of the reviewers |

**Outcomes:**

- one rejection rejects the approval; a request for changes returns it;
- if the remaining reviewers can no longer reach the policy, the approval is rejected as "insufficient";
- `abstain: true` lets people abstain without counting either way.

**Sequential approvals** list `Stage` children, which run in order and each have their own reviewers and policy.

**Rules:**

- people decide only once per stage and round (enforced by a unique index);
- initiators may not decide on their own request unless `selfApproval: true`;
- comments are required for rejections and change requests (configurable);
- with `delegation: true`, reviewers can pass their decision on (up to `max_delegations` times).

When an approval completes, its outcome transition (`onApprove`, `onReject`, `onChanges`) runs as the `approvals` system actor. Leave `onApprove` out and use `requiresApproval:` on a human transition when a person should take the final step.

## Human tasks, assignment and delegation

<!-- example:task -->
```php
use Gcego\Security\Requirements\Ability;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Tasks\Task;

return new State('setup', 'Setup',
    new Task('prepare-room', 'Prepare the room', new Ability('facilities.manage'), due: '2 days', priority: 'high'),
);
```

How tasks are assigned:

- **Assigned tasks:** `assign:` names an `AssignmentResolver`; each person must belong to the instance's tenant and satisfy the task's requirements, or they are left out. Resolvers can never widen access.
- **Pool tasks:** without a resolver, eligible people claim the task. A pool step with no requirements is open to workflow managers only, never to everyone.

`completes:` names the transition that runs, as the `tasks` system actor, once the task is completed. Managers can `reassign` open work to eligible people. Assignees are notified through gcego/notifications.

## Deadlines and escalations

- **`Deadline('7 days', then: 'expire')`** runs the transition as the `deadlines` system actor. The transition must name `deadlines` in `systemActors`.
- **`Deadline(..., escalate: Supervisors::class)`** moves the state's open tasks to the resolved people. They must be eligible: escalation never widens access.
- **Task and approval `due:` dates:** overdue work is marked once and its assignee notified. Approvals with `escalate:` move overdue decisions as well.

`gcego:workflows:process-deadlines` processes due work in batches, each instance in its own locked command. It is scheduled every five minutes, without overlapping and on one server. Each deadline runs once. A refused `then` transition is recorded with its reason, not retried forever, and errors are reported (non-zero exit code).

## Automation

```php
new State('submitted', new Automation('score', ScoreApplication::class));            // queued (default)
new Transition('publish', 'approved', 'published', new Automation('announce', Announce::class, queue: false));
```

A `WorkflowHandler::handle(WorkflowContext): ?string` handler is trusted code named by definitions only. Automation runs:

- after the transaction commits: on the `workflows` queue, or immediately after the commit with `queue: false`;
- as the `automation` system actor, with no user, in the instance's tenant;
- once per scheduling: duplicate deliveries are skipped;
- for state automation, only while that state visit lasts.

A handler may return the name of the next transition, which must name the `automation` system actor. Queued failures are retried (`automation.tries`, with backoff) and recorded in the history once the retries are exhausted. A failure of automation run right after the commit is recorded without failing the committed request. Handlers must be idempotent.

## Authorization, system actors and tenancy

Every check is deny by default:

- **Subject policies:** people need the subject's Laravel policy ability. A subject without a policy allows nothing.
  - `startAbility`, `viewAbility` and `manageAbility` on the workflow (defaults: update, view, update);
  - `ability` on each transition.
- **Tenancy:** people must be members of the instance's tenant (gcego/security), and all queries are scoped to the current tenant. Other tenants' instances are "not found".
- **Requirements** on workflows, transitions, stages and tasks are evaluated as that person, in the instance's tenant.
- **System actors** (`deadlines`, `automation`, `approvals`, `tasks`, or your own name) may run only transitions that name them, or the outcomes the definition wires to approvals and tasks. They are never users and are recorded as such.
- **Managers:** the `gcego-workflows.manage` Gate ability may manage any instance it can see.

See [docs/security.md](docs/security.md).

## Concurrency, idempotency and versioning

**Concurrency.** Each command runs in a transaction that locks the instance row and saves it with a compare-and-swap on `lock_version`. A concurrent writer therefore makes it roll back with a 409. Pass the version the user saw as `expectedVersion:` to refuse stale screens. A unique index allows only one running instance per workflow and subject, unless `concurrent: true`.

**Idempotency.** Commands with an `idempotencyKey:` return their first result, flagged `replayed`, when repeated.

**Versioning.** Instances keep the `version` and structure hash they started with:

- the same version continues;
- another version continues only when the current definition lists it in `compatible:` and still has the instance's state;
- otherwise the command is refused (`WorkflowDefinitionException`). Migrate those instances explicitly; they are never reinterpreted silently.

`gcego:workflows:inspect` lists incompatible running versions.

## History and observability

`gcego_workflow_events` is append-only: the model refuses updates and deletes. Events are numbered per instance and carry the actor (user, system actor name), the states, the task, scalar metadata (comments, decisions, outcomes) and a correlation ULID per command.

After commit, the package dispatches:

- `WorkflowEventRecorded` for every event;
- `TransitionCompleted` for transitions;
- `WorkflowCommandRefused` for refused commands. This carries no subject data or comments.

Setting `observability.log_channel` adds structured logs.

`php artisan gcego:workflows:inspect [--json] [--definitions]` reports:

- definitions: versions, problems, unreachable states;
- running instances per status;
- overdue tasks and due deadlines;
- automation failures;
- incompatible versions.

## User interface

Livewire components:

| Component | Usage | Shows |
| --- | --- | --- |
| `gcego-workflow-inbox` | | the user's approvals and tasks: filtered, paginated; pool tasks can be claimed |
| `gcego-workflow-panel` | `:instance="$ulid"`, or `:subject="$record" workflow="…"` | state, available transitions (with reasons), comment, start, and the user's approvals and tasks |
| `gcego-workflow-approval` | `:task="$ulid"` | approve, reject, request changes, abstain, complete, delegate (to eligible reviewers only) |
| `gcego-workflow-timeline` | `:instance="$ulid"` | the history, newest first, comments escaped |

In every gcego/panels panel, `<panel>/workflows` is the inbox and `<panel>/workflows/{instance}` an instance page. They sit behind the panel's own checks, plus the instance's.

The browser sends only:

- transition names;
- comments;
- the version it saw (locked properties).

Everything else is re-read and re-authorized on the server.

The views reuse classes the other GCEGO packages already compile, so the shared stylesheet does not grow. Slots on `Workflow::class` are inbox, card, item, empty, panel, actions, approval and timeline. Buttons use the `control.button` presets.

## Integrations

| Package | What workflows provides |
| --- | --- |
| gcego/actions | `Integration\Actions\StartWorkflow` and `RunWorkflowTransition` handlers (refusals become validation errors). A validated `comment` input becomes the transition comment. |
| gcego/tables | `Columns\WorkflowStateColumn` (`Workflow`, `Eager` with the `HasWorkflows` trait) |
| gcego/infolists | `Entries\WorkflowStateEntry` |
| gcego/widgets | `Integration\Widgets\WorkflowStateCounts` (extend it per workflow; protect the widget) and `PendingWorkCount` |
| gcego/navigation | `Integration\Navigation\InboxBadge` |
| gcego/notifications | Assignment, overdue and outcome messages (category `workflows`, database channel, tenant-scoped, idempotent) |
| gcego/panels | Inbox and instance pages |
| gcego/registry | The `workflow` kind and discovery of `app/GCEGo/Workflows` |

Studio integration waits for gcego/studio (not installed yet).

## Configuration

`config/gcego-workflows.php` covers:

- discovery paths;
- execution: optimistic locking, connection;
- approvals: comments, self-approval, delegation limit;
- automation: enabled, queue, connection, tries;
- deadlines: enabled, schedule, batch;
- notifications;
- history and inbox page sizes;
- observability log channel;
- security: tenant scoping, manage ability;
- panels.

Values are validated at boot. No option disables server-side authorization: even with `enforce_tenant_scope` off, tenant membership is still checked.

## Accessibility

The views target WCAG 2.2 AA:

- every control has a visible label;
- states are always shown as text (never color alone), with the instance status for screen readers;
- disabled transitions explain why (`aria-describedby`);
- results and errors are announced (`role="status"`, `role="alert"`);
- buttons are real buttons with focus styles; destructive steps confirm;
- history and lists use list semantics and `<time datetime>`.

## Testing and troubleshooting

```bash
composer test        # Pest (Unit, Integration, Feature, Security)
composer analyse     # PHPStan max
composer format      # Pint
```

| Symptom | Cause and fix |
| --- | --- |
| "Definition workflow:… is invalid" | The message lists every problem, for example a transition to an unknown state, or a deadline transition that doesn't name `deadlines`. |
| A transition is missing from the panel | The user lacks the ability, the transition is system-only, or it doesn't leave the current state. Disabled ones show their reason. |
| "This workflow changed while you were looking at it" | Someone else changed the instance; the panel reloads its version. |
| Automation never runs | No worker for the `workflows` queue, or `automation.enabled` is false (a warning is logged). |
| Deadlines never fire | The scheduler isn't running, or `deadlines.enabled` / `deadlines.schedule` is false. |
| Version refused after a deploy | List the old version in `compatible:` if its states still fit; otherwise migrate those instances. |
