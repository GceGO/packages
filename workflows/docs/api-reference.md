# API reference

## Definitions

| Class | Key arguments (named) | Children |
| --- | --- | --- |
| `Workflow` | `name`, `title`, `description`, `version`, `compatible`, `initial`, `subject`, `startAbility`, `viewAbility`, `manageAbility`, `concurrent` | `State`, `Transition`, `Approval`, requirements |
| `States\State` | `name`, `label`, `color`, `icon`, `terminal`, `metadata` | `Task`, `Deadline`, `Automation` |
| `Transitions\Transition` | `name`, `from`, `to`, `guard`, `ability`, `confirmation`, `comment`, `requiresApproval`, `systemActors`, `human`, `reopen` | `Branch`, `Automation`, requirements |
| `Transitions\Branch` | `to`, `condition` | — |
| `Approvals\Approval` | `name`, `label`, `state`, `reviewers`, `policy`, `requiredApprovals`, `onApprove`, `onReject`, `onChanges`, `due`, `escalate`, `selfApproval`, `delegation`, `abstain` | `Stage`, requirements |
| `Approvals\Stage` | `name`, `label`, `reviewers`, `policy`, `requiredApprovals` | requirements |
| `Tasks\Task` | `name`, `label`, `assign`, `due`, `priority`, `completes` | requirements |
| `Scheduling\Deadline` | `after`, `then`, `escalate` | — |
| `Automation\Automation` | `name`, `handler`, `queue` | — |

Allowed values:

- **Durations:** `N minutes|hours|days|weeks`.
- **Policies:** `any`, `all`, `quorum`, `majority`.
- **Colors:** `neutral`, `info`, `success`, `warning`, `danger`.
- **Priorities:** `low`, `normal`, `high`, `urgent`.
- **Comment rules:** `none`, `optional`, `required`.

## Contracts

| Contract | Method | When it runs |
| --- | --- | --- |
| `Contracts\TransitionGuard` | `check(WorkflowContext): GuardResult\|bool` | inside the transaction |
| `Contracts\Condition` | `passes(WorkflowContext): bool` | |
| `Contracts\AssignmentResolver` | `resolve(WorkflowContext): iterable<Model>` | |
| `Contracts\WorkflowHandler` | `handle(WorkflowContext): ?string` | after commit; returns the next transition, or null |

`WorkflowContext` provides `instance`, `workflow`, `actor`, `transition`, `comment` and `input`, plus `subject()`, `state()`, `tenant()`, `user()` and `isSystem()`.

## Services

| Service | Methods |
| --- | --- |
| `Engine\WorkflowEngine` | `start`, `transition`, `decide`, `claim`, `completeTask`, `delegate`, `reassign`, `suspend`, `resume`, `cancel`, `available`, `running`, `canWorkOn`, `requirementsOf` |
| `Instances\WorkflowRepository` | `find($ulid, $viewer)`, `findTask`, `canView`, `canManage`, `forSubject`, `latestFor`, `history` |
| `Tasks\Inbox` | `for($user, $page, $filter)`, `count($user)` |
| `Registry\WorkflowDefinitions` | `names`, `has`, `resolve`, `forInstance` |
| `Diagnostics\WorkflowInspector` | `report($runtime = true)` |
| `Actors\SystemActor` | `named($name)`; constants `Deadlines`, `Automation`, `Approvals`, `Tasks` |

## Results and exceptions

`Engine\TransitionResult` has the fields `instance`, `transition`, `from`, `to`, `status`, `version`, `correlation` and `replayed`, and a `toArray()` method.

| Exception | Meaning |
| --- | --- |
| `InvalidTransitionException` | Refused, with a `reason` code: `state`, `suspended`, `finished`, `status`, `approval`, `comment`, `guard`, `task_closed`, `self_approval`, `abstain`, `already_decided`, `decision`, `claim`, `delegation`, `delegation_limit` or `assignee` |
| `WorkflowAuthorizationException` | 403 |
| `WorkflowConflictException` | 409 |
| `WorkflowNotFoundException` | 404 |
| `WorkflowDefinitionException` | an invalid definition or configuration |

## Events

| Event | Fields |
| --- | --- |
| `WorkflowEventRecorded` | `instance`, `workflow`, `tenant`, `type`, `sequence`, `correlation`, `from`, `to` |
| `TransitionCompleted` | `workflow`, `tenant`, `result`, `actorKind` |
| `WorkflowCommandRefused` | `command`, `instance`, `workflow`, `reason`, `actorKind` |

History event types (`History\EventType`):

- `workflow.*`: started, transitioned, completed, suspended, resumed, cancelled, reopened;
- `approval.*`: opened, stage_completed, completed, decided;
- `task.*`: created, claimed, completed, delegated, reassigned, overdue, escalated;
- `deadline.reached`;
- `automation.*`: completed, failed.

## Commands, routes and components

- **Commands:** `gcego:workflows:process-deadlines` and `gcego:workflows:inspect {--json} {--definitions}`.
- **Panel routes:** `<panel>/workflows` (`<namespace>workflows`) and `<panel>/workflows/{instance}` (`<namespace>workflows.instance`).
- **Livewire components:** `gcego-workflow-inbox`, `gcego-workflow-panel`, `gcego-workflow-approval` and `gcego-workflow-timeline`.
