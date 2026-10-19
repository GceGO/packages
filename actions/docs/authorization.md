# Authorization

Every execution, including every Livewire interaction, is authorized again
through gcego/security:

1. **Definition chain:** requirement nodes on the blueprint, any
   `ActionGroup` and the action must all allow (`DefinitionAuthorizer`). A path
   without any requirement is denied.
2. **Own authorization:** the action needs an `Ability` (built-ins have one) or
   its own requirement nodes.
3. **Ability:** checked for the record (record actions, and **every selected
   record** of bulk actions) or for the model class (Create), through the
   model's policy or a Gate ability.
4. **Tenant:** tenant-owned models (`gcego-security.tenancy.models`) need a
   selected tenant.

Records are resolved through the scoped source before step 3. A known
identifier from another institution is 404, never processed.

What is not authorization:

- a visible or enabled button;
- a confirmation;
- the action name;
- `Hidden` and `Disabled`, which only *add* restrictions.

To bind your own rules, bind `Contracts\ActionAuthorizer`.
