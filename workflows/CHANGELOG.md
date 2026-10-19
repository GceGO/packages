# Changelog

## Unreleased

- **Fixed:** state icons accept every Luminaries name (aliases, `provider:icon`) and typed icons. Previously only bare names were accepted.
- **Fixed:** the definition kind is registered when gcego/registry's `DefinitionKinds` is resolved, not during `register()`. With Laravel's package discovery (alphabetical order) this provider could run before gcego/registry bound the singleton, losing the kind — the package's definitions were then not discovered, and with gcego/workflows installed the application failed to boot.

## 0.1.0 - 2026-10-17

First release.

- **Definitions:**
  - `Workflow` (registry kind `workflow`, discovered from `app/GCEGo/Workflows`), `State`, `Transition`, `Branch`, `Approval`, `Stage`, `Task`, `Deadline` and `Automation`;
  - named or `Property` arguments, normalized to one definition;
  - structural validation (unknown or duplicate states, terminal exits, outcome wiring, deadline actors);
  - unreachable-state diagnostics, structure fingerprints, versions with `Compatible` continuation.
- **Engine:** `WorkflowEngine`:
  - commands: start, transition, decide, claim, complete, delegate, reassign, suspend, resume, cancel, available;
  - `CommandRunner`: row locks, compare-and-swap `lock_version`, expected versions, idempotency keys, tenant scope, after-commit release of effects;
  - `TransitionExecutor`: state, authorization, required approval, comment rule, guard, branches;
  - `StateEntry`: closes old work; opens tasks, approvals, deadlines and automation.
- **Approvals:** `ApprovalCoordinator`:
  - policies `any`, `all`, `quorum` and `majority`; sequential stages; rounds;
  - decisions (approve, reject, request changes, abstain), each once per person, stage and round;
  - insufficiency rejection, self-approval protection, delegation limits;
  - outcome transitions run as the `approvals` system actor.
- **Tasks:**
  - assigned (`AssignmentResolver`, filtered by tenant and requirements) and pool tasks;
  - claim, complete (`completes` runs as the `tasks` system actor), delegate and reassign;
  - `Inbox`, which uses the same eligibility rules as the commands.
- **Deadlines:** `DeadlineProcessor` and `gcego:workflows:process-deadlines`:
  - scheduled every five minutes, without overlapping, on one server;
  - state deadlines (`then`, `escalate`), overdue tasks and approval escalation, each processed once.
- **Automation:** `AutomationDispatcher`, `ExecuteWorkflowAutomation` and `AutomationRunner`:
  - queued after commit, or run right after it;
  - runs as the `automation` system actor in the instance's tenant;
  - once per scheduling, skipped for left state visits;
  - retried, with failures recorded.
- **Security:** `WorkflowAuthorizer`:
  - deny by default: subject policies, gcego/security requirements evaluated as the person, tenant membership, the `gcego-workflows.manage` Gate ability;
  - `SystemActor` limited to the transitions that name it or are wired to it.
- **History:**
  - append-only `WorkflowEvent` with per-instance sequences and command correlation;
  - events `WorkflowEventRecorded`, `TransitionCompleted` and `WorkflowCommandRefused`, plus optional structured logs.
- **Storage:** migrations for instances, tasks, decisions, events and command records, with string morph keys.
- **UI:**
  - Livewire `gcego-workflow-inbox`, `gcego-workflow-panel`, `gcego-workflow-approval` and `gcego-workflow-timeline`;
  - panel pages `<panel>/workflows` and `<panel>/workflows/{instance}`;
  - styling slots, built only from classes already compiled (no stylesheet growth);
  - English and French translations.
- **Integrations:**
  - gcego/actions handlers `StartWorkflow` and `RunWorkflowTransition`;
  - gcego/tables `WorkflowStateColumn` and gcego/infolists `WorkflowStateEntry`, with the `HasWorkflows` eager relation;
  - gcego/widgets `WorkflowStateCounts` and `PendingWorkCount`; gcego/navigation `InboxBadge`;
  - gcego/notifications messages (category `workflows`).
- **Diagnostics:** `WorkflowInspector` and `gcego:workflows:inspect`.
