{{-- gcego-vault::livewire.roles — the roles overview. --}}
<div class="{{ implode(' ', $p::Screen) }}" data-vault-screen="roles">
    <x-gcego-vault::header :title="__('Roles')" :lead="__('Roles bundle permissions. People get access by holding roles; nothing is allowed by default.')" :scope="$scopeLabel">
        <x-slot:actions>
            @if ($can['export'])
                <button type="button" wire:click="export" class="{{ $s->button('secondary') }}">{{ __('Export') }}</button>
            @endif
            @if ($can['create'])
                <a href="{{ $this->url('roles.create') }}" class="{{ $s->button('primary') }}">{{ __('New role') }}</a>
            @endif
        </x-slot:actions>
    </x-gcego-vault::header>

    @include('gcego-vault::partials.feedback')

    <section class="{{ implode(' ', $p::Card) }}" aria-label="{{ __('Roles') }}">
        <div class="{{ implode(' ', $p::Toolbar) }}">
            <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center">
                <label class="sr-only" for="vault-roles-search">{{ __('Search roles') }}</label>
                <input id="vault-roles-search" type="search" wire:model.live.debounce.300ms="search" maxlength="100" placeholder="{{ __('Search by name or description') }}" class="{{ $s->input('sm:max-w-xs') }}">
                <label class="sr-only" for="vault-roles-status">{{ __('Status') }}</label>
                <select id="vault-roles-status" wire:model.live="status" class="{{ $s->input('sm:w-40') }}">
                    <option value="active">{{ __('Active') }}</option>
                    <option value="archived">{{ __('Archived') }}</option>
                    <option value="all">{{ __('All') }}</option>
                </select>
                @if ($groups !== [])
                    <label class="sr-only" for="vault-roles-group">{{ __('Group') }}</label>
                    <select id="vault-roles-group" wire:model.live="group" class="{{ $s->input('sm:w-48') }}">
                        <option value="">{{ __('All groups') }}</option>
                        @foreach ($groups as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                    </select>
                @endif
            </div>
            <p class="shrink-0 text-sm text-gcego-text-muted" wire:loading.remove>{{ trans_choice(':count role|:count roles', $roles->total(), ['count' => $roles->total()]) }}</p>
            <p class="shrink-0 text-sm text-gcego-text-muted" wire:loading>{{ __('Loading…') }}</p>
        </div>

        @if ($roles->isEmpty())
            <div class="{{ implode(' ', $p::Empty) }}">
                <p class="font-medium text-gcego-text">{{ __('No roles match.') }}</p>
                <p>{{ __('Change the search or filters, or create a role.') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="{{ implode(' ', $p::Table) }}">
                    <thead>
                        <tr>
                            @foreach (['name' => __('Role'), 'permissions_count' => __('Permissions'), 'holders_count' => __('Holders'), 'updated_at' => __('Changed')] as $column => $label)
                                <th scope="col" class="{{ implode(' ', $p::Th) }}" aria-sort="{{ $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                    <button type="button" wire:click="sortBy('{{ $column }}')" class="inline-flex items-center gap-1 uppercase hover:text-gcego-text focus-visible:outline-2 focus-visible:outline-gcego-ring rounded-sm">{{ $label }}@if ($sort === $column)<span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>@endif</button>
                                </th>
                            @endforeach
                            <th scope="col" class="{{ implode(' ', $p::Th) }}"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gcego-border">
                        @php($currentGroup = false)
                        @foreach ($roles as $role)
                            @if ($sort === 'name' && $currentGroup !== $role->group && $groups !== [])
                                @php($currentGroup = $role->group)
                                <tr><th colspan="5" scope="colgroup" class="bg-gcego-surface-muted px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gcego-text-muted">{{ $role->group ?? __('Ungrouped') }}</th></tr>
                            @endif
                            <tr class="{{ implode(' ', $p::Row) }}" wire:key="role-{{ $role->id }}" data-vault-role="{{ $role->name }}">
                                <td class="{{ implode(' ', $p::Td) }}">
                                    <a href="{{ $this->url('roles.show', ['role' => $role->id]) }}" class="{{ implode(' ', $p::Link) }}">{{ $role->label }}</a>
                                    <span class="ml-1 font-mono text-xs text-gcego-text-muted">{{ $role->name }}</span>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @if ($role->is_system)@include('gcego-vault::partials.badge', ['tone' => 'info', 'text' => __('System')])@endif
                                        @if ($role->is_protected)@include('gcego-vault::partials.badge', ['tone' => 'warning', 'text' => __('Protected')])@endif
                                        @if ($role->is_super)@include('gcego-vault::partials.badge', ['tone' => 'danger', 'text' => __('Super administrator')])@endif
                                        @if ($role->tenant !== '')@include('gcego-vault::partials.badge', ['tone' => 'primary', 'text' => __('This organization')])@endif
                                        @if ($role->panel !== null)@include('gcego-vault::partials.badge', ['tone' => 'neutral', 'text' => __('Panel: :panel', ['panel' => $role->panel])])@endif
                                        @if ($role->archived_at !== null)@include('gcego-vault::partials.badge', ['tone' => 'neutral', 'text' => __('Archived')])@endif
                                    </div>
                                    @if ($role->parents->isNotEmpty())
                                        <p class="mt-1 text-xs text-gcego-text-muted">{{ __('Inherits :roles', ['roles' => $role->parents->pluck('label')->implode(', ')]) }}</p>
                                    @endif
                                </td>
                                <td class="{{ implode(' ', $p::Td) }} tabular-nums">{{ $role->permissions_count }}</td>
                                <td class="{{ implode(' ', $p::Td) }} tabular-nums">{{ $role->holders_count }}</td>
                                <td class="{{ implode(' ', $p::Td) }} whitespace-nowrap text-gcego-text-muted"><time datetime="{{ $role->updated_at?->toIso8601String() }}">{{ $role->updated_at?->diffForHumans() }}</time></td>
                                <td class="{{ implode(' ', $p::Td) }} text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @if ($can['create'])
                                            <button type="button" wire:click="startDuplicate({{ $role->id }})" class="{{ $s->button('secondary', 'text-xs') }}">{{ __('Duplicate') }}</button>
                                        @endif
                                        @if ($can['delete'] && ! $role->is_protected && ! $role->is_super)
                                            @if ($role->archived_at === null)
                                                <button type="button" wire:click="confirm('archive', {{ $role->id }})" class="{{ $s->button('secondary', 'text-xs') }}">{{ __('Archive') }}</button>
                                            @else
                                                <button type="button" wire:click="restore({{ $role->id }})" class="{{ $s->button('secondary', 'text-xs') }}">{{ __('Restore') }}</button>
                                            @endif
                                            @if (! $role->is_system)
                                                <button type="button" wire:click="confirm('delete', {{ $role->id }})" class="{{ $s->button('destructive', 'text-xs') }}">{{ __('Delete') }}</button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gcego-border p-4">{{ $roles->links() }}</div>
        @endif
    </section>

    @if ($duplicating !== null)
        <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-duplicate-title">
            <h2 id="vault-duplicate-title" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Duplicate a role') }}</h2>
            <form wire:submit="duplicate" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="vault-duplicate-name" class="{{ implode(' ', $p::Label) }}">{{ __('Name of the copy') }}</label>
                    <input id="vault-duplicate-name" type="text" wire:model="duplicateName" maxlength="100" class="{{ $s->input() }}" aria-describedby="vault-duplicate-hint">
                    <p id="vault-duplicate-hint" class="{{ implode(' ', $p::Hint) }}">{{ __('Lowercase letters and digits, separated by "-" or "_".') }}</p>
                    @error('name')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="cancelDuplicate" class="{{ $s->button('secondary') }}">{{ __('Cancel') }}</button>
                    <button type="submit" class="{{ $s->button('primary') }}">{{ __('Create copy') }}</button>
                </div>
            </form>
        </section>
    @endif

    @if ($can['import'])
        <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-import-title">
            <h2 id="vault-import-title" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Import role definitions') }}</h2>
            <p class="{{ implode(' ', $p::Lead) }}">{{ __('A file exported from Vault. Every permission it grants must be one you hold; the import is applied completely or not at all. System roles are never changed by an import.') }}</p>
            <form wire:submit="importRoles" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <label class="sr-only" for="vault-import-file">{{ __('Role file (JSON)') }}</label>
                <input id="vault-import-file" type="file" wire:model="import" accept="application/json,.json" class="text-sm text-gcego-text">
                <label class="inline-flex items-center gap-2 text-sm text-gcego-text"><input type="checkbox" wire:model="overwrite" class="{{ implode(' ', $p::Checkbox) }}"> {{ __('Update existing custom roles') }}</label>
                <button type="submit" class="{{ $s->button('secondary') }}" wire:loading.attr="disabled" wire:target="import,importRoles">{{ __('Import') }}</button>
            </form>
            @error('import')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
            @error('file')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
        </section>
    @endif

    <x-gcego-vault::confirm :show="$confirmRole !== null" :title="($confirming['action'] ?? null) === 'delete' ? __('Delete this role?') : __('Archive this role?')" :confirm="($confirming['action'] ?? null) === 'delete' ? __('Delete role') : __('Archive role')" action="confirmed" :danger="true">
        @if ($confirmRole !== null)
            {{ ($confirming['action'] ?? null) === 'delete'
                ? __('":role" will be removed permanently. The audit log keeps its history.', ['role' => $confirmRole->label])
                : __('Everyone holding ":role" loses its permissions until it is restored.', ['role' => $confirmRole->label]) }}
        @endif
    </x-gcego-vault::confirm>
</div>
