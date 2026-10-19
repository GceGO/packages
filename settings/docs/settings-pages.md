# Settings pages and the settings UI

## Pages

Every panel gets `<panel>/settings` (the first page the viewer may open)
and `<panel>/settings/{page}`, behind the panel's authentication, tenancy
and access checks. Groups without an explicit page get a default page at
the first scope their settings allow (global, tenant, panel, then user).

```php
new SettingsPage(name: 'school', title: 'School settings', group: 'institution', scope: SettingScope::Tenant, panels: ['school'], icon: 'building', sort: 5);
```

Embed outside a panel: `<livewire:gcego-settings-panel page="general" />` or
just the form: `<livewire:gcego-settings-form page="general" />`.

## The form (`SettingsForm`)

1. The page and its scope are resolved on the server, and the viewer must be
   allowed to open it.
2. Effective values are loaded. Each field is rendered through gcego/forms
   (one field per row), with its scope badge: *Set for …* (an override,
   with a *use the inherited value* button behind a confirmation dialog),
   *Inherited from …*, or *Default value*.
3. On save, gcego/forms normalizes and validates the submission and refuses
   keys that are not on the page. Only values that changed are sent, with
   the versions the form loaded, to `SettingsWriter`, which authorizes,
   validates, writes atomically, invalidates the cache, records history and
   dispatches events.
4. The result is shown in an `aria-live` status. Validation errors appear
   next to their fields. A concurrent change shows "These settings changed
   while you were editing them".

On every request, the scope is resolved again and compared with the scope
the form was opened for. Public state is `#[Locked]` except `$data`.

## Accessibility

Fields come from gcego/forms: labels, helper text and errors are linked
through `aria-describedby` and `aria-invalid`, and required markers include
screen-reader text. The panel uses a `nav` landmark with `aria-current`, a
labelled search, an `aria-live` region for results, tabs with
`role="tab"`/`tabpanel` and arrow keys, and a reset dialog with
`role="dialog"`, `aria-modal`, labelled title and description, focus on the
confirm button, and Escape to close. Focus is always visible. Scope badges
are text (never color alone). Loading text uses `motion-safe`.

## Alpine.js

Alpine handles the unsaved-changes indicator (and the browser's leave
warning), the reset confirmation, and arrow keys between tabs. Every
decision is made on the server.

## Styling

Slots on `SettingsGroup` (override with
`ChromaManager::override(SettingsGroup::class, new Slot('section', '...'))`):
`wrapper`, `sidebar`, `navigation`, `group`, `section`, `field`,
`description`, `scope`, `inherited`, `override`, `actions`, `history`,
`empty`, `loading`, `error`. The defaults are in `SettingsPresets` and use
theme tokens, so light and dark themes apply.

## Search

Search covers the settings the viewer may see in the current panel, by key,
label, description, group, section and keywords. It never searches values.
At most 25 results are returned, and each links to the field.

## History

The History tab lists changes at the page's scope instance, newest first,
20 per page: setting, change type, actor, time, and previous and new value
when the setting is not sensitive. Entries are append-only.

## Import and export

```php
$document = app(SettingsExporter::class)->export($scopes->forTenant(1), $user, groups: ['institution']);
$preview = app(SettingsImporter::class)->preview($document, $scopes->forTenant(2), $user);   // create, update (conflict), unchanged, refused
$result = app(SettingsImporter::class)->import($document, $scopes->forTenant(2), $user, overwrite: true);
```

## Actions (gcego/actions)

```php
final class SaveMailSettings extends SaveSettings
{
    protected function keys(): array { return ['mail_enabled', 'mail_from_address']; }
}

new Action('save-mail', 'Save', new Handler(SaveMailSettings::class), new Input(...));
```

`ResetSettings` works the same way for resets. Both write as the acting
user, at the scope `scope()` returns (resolved on the server).

## Approvals (gcego/workflows)

```php
new Setting(name: 'school_motto', default: '', approval: 'settings-approval');

return new Workflow('settings-approval', 'Settings change',
    new Property(Workflow::Subject, SettingRequest::class),
    new State('pending'), new State('approved', terminal: true), new State('refused', terminal: true),
    new Transition('approve', 'pending', 'approved', new Automation('apply', ApplySettingRequest::class, queue: false), ability: 'approve'),
    new Transition('refuse', 'pending', 'refused', new Automation('discard', DiscardSettingRequest::class, queue: false), ability: 'approve'),
);
```

A person's change becomes a `SettingRequest` (with its value encrypted) and
a workflow instance. Nothing is applied until approval. The `approve`
ability of `SettingRequestPolicy` allows anyone who may change that setting
at that scope, except the requester. Applying validates the change again
and refuses it when the setting changed since the request (status
`conflicted`). The requester is notified. Changes by `asSystem()` are not
routed. Switch approvals off with `gcego-settings.approvals.enabled`.

## Notifications and media

- Requesters hear when their change is applied, refused or could not be
  applied. Sensitive changes are announced to the people your
  `SettingsRecipients` class returns (`notifications.recipients`). Category
  `settings`; names and scopes only.
- A `Media` setting (`type: 'media'`) stores a gcego/media ULID, never file
  contents. The file must exist in the current tenant and be viewable by
  the person. `->media('school_logo')` returns the `MediaRecord` through
  gcego/media's own authorization.
