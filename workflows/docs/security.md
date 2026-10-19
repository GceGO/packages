# Security

## Threat model and guarantees

| Threat | Mitigation |
| --- | --- |
| A browser forces a state or transition | The UI sends only a transition name, a comment and the version it saw. The state is read from the locked database row, and the transition must leave it. Livewire's locked properties keep the instance id and version from being changed; tampering returns 419. |
| A browser claims to be another user or a system actor | Actors are passed by server code only: `auth()->user()` in components, `SystemActor` in jobs and commands. `input:` values never change the state or actor. |
| A user skips authorization | Every command checks the subject policy ability, the step's gcego/security requirements (as that user, in the instance's tenant) and tenant membership. A subject without a policy allows nothing. Errors deny. |
| A user reaches another tenant's instance | Lookups and inboxes are scoped to the current tenant; other tenants' instances are "not found". Membership is still checked when `enforce_tenant_scope` is off. |
| A user runs system-only transitions | `human: false` transitions refuse people. System actors run only transitions that name them, or the outcomes the definition wires to approvals and tasks. |
| Arbitrary code runs | Guards, conditions, resolvers and handlers are classes named by trusted definitions and validated to implement their contract. Jobs carry names, never class names from data. |
| Concurrent writers, double clicks, retries | Row locks, compare-and-swap `lock_version` (409), expected versions from screens, idempotency keys, one running instance per subject (unique index), one decision per person and round (unique index), one run per automation scheduling. |
| Irreversible effects before commit | Events, jobs and notifications are released after the commit and discarded on rollback, including when the command runs inside an outer transaction. |
| History tampering | `WorkflowEvent` refuses updates and deletes, and sequences are unique per instance. This is application-level immutability; for tamper evidence, restrict database privileges and ship events to an external log (`observability.log_channel`). |
| Self-approval and delegation abuse | Initiators may not decide on their own requests unless the approval allows it (default off). Delegation and escalation targets must be eligible: in the tenant, satisfying the requirements, not already assigned. The delegation chain is limited. |
| Escalation widens access | Escalation targets are filtered like assignees; ineligible targets are dropped and the drop is recorded. |
| Data leaks in notifications and logs | Notifications name the workflow, task and state, never subject data or comments. Refusal events and logs carry identifiers and reason codes only. |
| Silent failures | Automation failures are retried and recorded in the history. Deadline errors are reported and leave the item due, and the command exits non-zero. Disabled automation logs a warning. |

## Configuration that matters

- `security.manage_ability`: the Gate ability for workflow managers. Define it in your application (`Gate::define('gcego-workflows.manage', …)`); if it is not defined, nobody gains it.
- `approvals.allow_self_approval`: keep it false unless separation of duties doesn't apply.
- `security.enforce_tenant_scope`: keep it true. Turning it off widens queries, but membership is still checked.

## Notes

- The "one running instance" index relies on NULLs being distinct in unique indexes (MySQL, MariaDB, PostgreSQL and SQLite). On SQL Server, use a filtered index.
- Comments are stored in the history and shown to people who may view the instance. Do not put secrets in comments.
