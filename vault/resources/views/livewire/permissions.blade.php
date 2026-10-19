{{-- gcego-vault::livewire.permissions — the permission registry. --}}
<div class="{{ implode(' ', $p::Screen) }}" data-vault-screen="permissions">
    <x-gcego-vault::header :title="__('Permissions')" :lead="__('Everything the application can allow. Registering a permission grants it to nobody; roles and direct grants do.')" :scope="$scopeLabel">
        <x-slot:actions>
            @if ($canManage && ! $creating && $editing === null)
                <button type="button" wire:click="create" class="{{ $s->button('primary') }}">{{ __('Register permission') }}</button>
            @endif
        </x-slot:actions>
    </x-gcego-vault::header>

    @include('gcego-vault::partials.feedback')

    @if ($creating || $editing !== null)
        <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-permission-form">
            <h2 id="vault-permission-form" class="{{ implode(' ', $p::CardTitle) }}">{{ $editing === null ? __('Register a custom permission') : __('Edit permission') }}</h2>
            <p class="{{ implode(' ', $p::Lead) }}">{{ __('A custom permission only matters where code checks it (a policy, a requirement or $user->can()).') }}</p>
            <form wire:submit="save" class="mt-4 {{ implode(' ', $p::Grid) }}">
                <div>
                    <label for="vault-pf-name" class="{{ implode(' ', $p::Label) }}">{{ __('Name') }}</label>
                    <input id="vault-pf-name" type="text" wire:model="form.name" @disabled($editing !== null) maxlength="150" required class="{{ $s->input('font-mono') }}" placeholder="reports.export" aria-describedby="vault-pf-name-hint">
                    <p id="vault-pf-name-hint" class="{{ implode(' ', $p::Hint) }}">{{ __('Dot-separated, for example "subjects.publish" or "students.email.view".') }}</p>
                    @error('name')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="vault-pf-label" class="{{ implode(' ', $p::Label) }}">{{ __('Label') }}</label>
                    <input id="vault-pf-label" type="text" wire:model="form.label" maxlength="150" class="{{ $s->input() }}">
                    @error('label')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="vault-pf-group" class="{{ implode(' ', $p::Label) }}">{{ __('Group') }}</label>
                    <input id="vault-pf-group" type="text" wire:model="form.group" maxlength="100" list="vault-pf-groups" class="{{ $s->input() }}">
                    <datalist id="vault-pf-groups">@foreach ($groups as $option)<option value="{{ $option }}"></option>@endforeach</datalist>
                </div>
                <div>
                    <label for="vault-pf-scope" class="{{ implode(' ', $p::Label) }}">{{ __('Applies') }}</label>
                    <select id="vault-pf-scope" wire:model="form.scope" class="{{ $s->input() }}">
                        <option value="any">{{ __('Globally and inside organizations') }}</option>
                        <option value="global">{{ __('Globally only') }}</option>
                        <option value="tenant">{{ __('Inside organizations only') }}</option>
                    </select>
                    @error('scope')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="vault-pf-requires" class="{{ implode(' ', $p::Label) }}">{{ __('Requires (comma-separated)') }}</label>
                    <input id="vault-pf-requires" type="text" wire:model="form.requires" class="{{ $s->input('font-mono') }}" placeholder="reports.view">
                    @error('requires')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="vault-pf-description" class="{{ implode(' ', $p::Label) }}">{{ __('Description') }}</label>
                    <textarea id="vault-pf-description" wire:model="form.description" rows="2" maxlength="2000" class="{{ $s->input() }}"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gcego-text md:col-span-2"><input type="checkbox" wire:model="form.sensitive" class="{{ implode(' ', $p::Checkbox) }}"> {{ __('Sensitive (high-risk; may require approval)') }}</label>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" wire:click="cancel" class="{{ $s->button('secondary') }}">{{ __('Cancel') }}</button>
                    <button type="submit" class="{{ $s->button('primary') }}">{{ __('Save') }}</button>
                </div>
            </form>
        </section>
    @endif

    <section class="{{ implode(' ', $p::Card) }}" aria-label="{{ __('Registered permissions') }}">
        <div class="{{ implode(' ', $p::Toolbar) }}">
            <div class="flex w-full flex-col gap-3 sm:flex-row">
                <label class="sr-only" for="vault-perm-search">{{ __('Search permissions') }}</label>
                <input id="vault-perm-search" type="search" wire:model.live.debounce.300ms="search" maxlength="100" placeholder="{{ __('Search by name or label') }}" class="{{ $s->input('sm:max-w-xs') }}">
                <label class="sr-only" for="vault-perm-group">{{ __('Group') }}</label>
                <select id="vault-perm-group" wire:model.live="group" class="{{ $s->input('sm:w-48') }}"><option value="">{{ __('All groups') }}</option>@foreach ($groups as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
                <label class="sr-only" for="vault-perm-source">{{ __('Source') }}</label>
                <select id="vault-perm-source" wire:model.live="source" class="{{ $s->input('sm:w-44') }}">
                    <option value="">{{ __('Every source') }}</option>
                    <option value="discovered">{{ __('Discovered') }}</option>
                    <option value="config">{{ __('Configured') }}</option>
                    <option value="custom">{{ __('Custom') }}</option>
                </select>
            </div>
            <p class="shrink-0 text-sm text-gcego-text-muted">{{ trans_choice(':count permission|:count permissions', $permissions->total(), ['count' => $permissions->total()]) }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="{{ implode(' ', $p::Table) }}">
                <thead><tr>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Permission') }}</th>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Group') }}</th>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Applies') }}</th>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Requires') }}</th>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Roles') }}</th>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr></thead>
                <tbody class="divide-y divide-gcego-border">
                    @forelse ($permissions as $permission)
                        <tr class="{{ implode(' ', $p::Row) }}" wire:key="permission-{{ $permission->id }}" data-vault-permission="{{ $permission->name }}">
                            <td class="{{ implode(' ', $p::Td) }}">
                                <span class="block">{{ $permission->label }}</span>
                                <span class="block break-all font-mono text-xs text-gcego-text-muted">{{ $permission->name }}</span>
                                <span class="mt-1 flex flex-wrap gap-1">
                                    @include('gcego-vault::partials.badge', ['tone' => $permission->source === 'custom' ? 'primary' : 'neutral', 'text' => ['config' => __('Configured'), 'discovered' => __('Discovered'), 'custom' => __('Custom')][$permission->source] ?? $permission->source])
                                    @if ($permission->is_sensitive)@include('gcego-vault::partials.badge', ['tone' => 'warning', 'text' => __('Sensitive')])@endif
                                    @if ($permission->source !== 'custom' && $latestDeclared !== null && ($permission->declared_at === null || $permission->declared_at->lt($latestDeclared)))@include('gcego-vault::partials.badge', ['tone' => 'danger', 'text' => __('No longer declared')])@endif
                                </span>
                            </td>
                            <td class="{{ implode(' ', $p::Td) }} text-gcego-text-muted">{{ $permission->group ?? '—' }}</td>
                            <td class="{{ implode(' ', $p::Td) }} whitespace-nowrap text-gcego-text-muted">{{ ['any' => __('Everywhere'), 'global' => __('Globally'), 'tenant' => __('Organizations')][$permission->scope] ?? $permission->scope }}</td>
                            <td class="{{ implode(' ', $p::Td) }} font-mono text-xs text-gcego-text-muted">{{ implode(', ', $permission->dependencies()) ?: '—' }}</td>
                            <td class="{{ implode(' ', $p::Td) }} tabular-nums">{{ $permission->roles_count }}</td>
                            <td class="{{ implode(' ', $p::Td) }} text-right">
                                @if ($canManage && $permission->source === 'custom')
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="edit({{ $permission->id }})" class="{{ $s->button('secondary', 'text-xs') }}">{{ __('Edit') }}</button>
                                        <button type="button" wire:click="confirmDelete({{ $permission->id }})" class="{{ $s->button('destructive', 'text-xs') }}">{{ __('Delete') }}</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="{{ implode(' ', $p::Empty) }}">{{ __('No permission matches. Run "php artisan gcego:vault:sync" to register the application\'s permissions.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gcego-border p-4">{{ $permissions->links() }}</div>
    </section>

    <x-gcego-vault::confirm :show="$deletingPermission !== null" :title="__('Delete this permission?')" :confirm="__('Delete')" action="delete" :danger="true">
        {{ __('":name" will be removed. Permissions still granted by a role or to a person cannot be deleted.', ['name' => $deletingPermission?->name ?? '']) }}
    </x-gcego-vault::confirm>
</div>
