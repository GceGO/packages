{{-- gcego-vault::livewire.role-editor — create or edit a role. --}}
<div class="{{ implode(' ', $p::Screen) }}" data-vault-screen="role-editor"
     x-data="{ dirty: false }"
     x-on:input="dirty = true" x-on:change="dirty = true"
     x-init="window.addEventListener('beforeunload', (event) => { if (dirty) { event.preventDefault(); event.returnValue = ''; } })">
    <x-gcego-vault::header :title="$role === null ? __('New role') : __('Edit :role', ['role' => $role->label])" :lead="__('Give the role only the permissions it needs. You can only grant permissions you hold yourself.')" :scope="$scopeLabel">
        <x-slot:actions>
            <a href="{{ $role === null ? $this->url('roles') : $this->url('roles.show', ['role' => $role->id]) }}" class="{{ $s->button('secondary') }}">{{ __('Cancel') }}</a>
        </x-slot:actions>
    </x-gcego-vault::header>

    @include('gcego-vault::partials.feedback')

    <form wire:submit="save" x-on:submit="dirty = false" class="flex flex-col gap-6">
        <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-role-details">
            <h2 id="vault-role-details" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Details') }}</h2>
            <div class="mt-4 {{ implode(' ', $p::Grid) }}">
                <div>
                    <label for="vault-role-label" class="{{ implode(' ', $p::Label) }}">{{ __('Label') }}</label>
                    <input id="vault-role-label" type="text" wire:model="label" required maxlength="150" class="{{ $s->input() }}">
                    @error('label')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="vault-role-name" class="{{ implode(' ', $p::Label) }}">{{ __('Identifier') }}</label>
                    <input id="vault-role-name" type="text" wire:model="name" required maxlength="100" pattern="[a-z][a-z0-9]*([\-_][a-z0-9]+)*" @disabled($role?->is_system) class="{{ $s->input('font-mono') }}" aria-describedby="vault-role-name-hint">
                    <p id="vault-role-name-hint" class="{{ implode(' ', $p::Hint) }}">{{ $role?->is_system ? __('System roles keep their identifier.') : __('Lowercase, for example "head-of-department".') }}</p>
                    @error('name')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="vault-role-description" class="{{ implode(' ', $p::Label) }}">{{ __('Description') }}</label>
                    <textarea id="vault-role-description" wire:model="description" rows="2" maxlength="2000" class="{{ $s->input() }}"></textarea>
                    @error('description')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="vault-role-group" class="{{ implode(' ', $p::Label) }}">{{ __('Group') }}</label>
                    <input id="vault-role-group" type="text" wire:model="group" maxlength="100" class="{{ $s->input() }}" placeholder="{{ __('For example "Teaching"') }}">
                </div>
                <div>
                    <label for="vault-role-panel" class="{{ implode(' ', $p::Label) }}">{{ __('Limit to a panel') }}</label>
                    <select id="vault-role-panel" wire:model="panel" class="{{ $s->input() }}" aria-describedby="vault-role-panel-hint">
                        <option value="">{{ __('Every panel') }}</option>
                        @foreach ($panels as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                    </select>
                    <p id="vault-role-panel-hint" class="{{ implode(' ', $p::Hint) }}">{{ __('A panel-limited role grants its permissions only inside that panel.') }}</p>
                    @error('panel')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        @if ($locked)
            <p class="{{ implode(' ', [...$p::Notice, ...$p::NoticeTones['info']]) }}">{{ __('This role\'s permissions and inheritance are managed by the application configuration; only its details can be edited here.') }}</p>
        @else
            <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-role-inherits">
                <h2 id="vault-role-inherits" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Inherits') }}</h2>
                <p class="{{ implode(' ', $p::Lead) }}">{{ __('The role also gets every permission of the roles chosen here (and of the roles they inherit).') }}</p>
                <fieldset class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <legend class="sr-only">{{ __('Inherited roles') }}</legend>
                    @forelse ($parents as $parent)
                        <label class="flex items-center gap-2 text-sm text-gcego-text" wire:key="parent-{{ $parent->id }}">
                            <input type="checkbox" value="{{ $parent->name }}" wire:model.live="inherits" class="{{ implode(' ', $p::Checkbox) }}"> {{ $parent->label }} <span class="font-mono text-xs text-gcego-text-muted">{{ $parent->name }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-gcego-text-muted">{{ __('No other role can be inherited here.') }}</p>
                    @endforelse
                </fieldset>
                @error('inherits')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
            </section>

            <section class="{{ implode(' ', $p::Card) }}" aria-labelledby="vault-role-permissions">
                <div class="{{ implode(' ', $p::Toolbar) }}">
                    <div>
                        <h2 id="vault-role-permissions" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Permissions') }}</h2>
                        <p class="text-sm text-gcego-text-muted">{{ trans_choice(':count chosen|:count chosen', count($permissions), ['count' => count($permissions)]) }} · {{ trans_choice(':count inherited|:count inherited', count($inherited), ['count' => count($inherited)]) }}</p>
                    </div>
                    <label class="sr-only" for="vault-role-filter">{{ __('Filter permissions') }}</label>
                    <input id="vault-role-filter" type="search" wire:model.live.debounce.250ms="filter" maxlength="100" placeholder="{{ __('Filter permissions') }}" class="{{ $s->input('sm:max-w-xs') }}">
                </div>

                @if ($missing !== [])
                    <div class="{{ implode(' ', [...$p::Notice, ...$p::NoticeTones['info']]) }} m-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between" role="status">
                        <p>{{ __('Also needed: :permissions', ['permissions' => implode(', ', $missing)]) }}</p>
                        <button type="button" wire:click="addDependencies" class="{{ $s->button('secondary', 'text-xs') }}">{{ __('Add them') }}</button>
                    </div>
                @endif
                @error('permissions')<p class="{{ implode(' ', $p::Error) }} px-4">{{ $message }}</p>@enderror

                <div class="divide-y divide-gcego-border">
                    @forelse ($groups as $groupName => $items)
                        <fieldset class="p-4" wire:key="group-{{ md5($groupName) }}">
                            <legend class="flex w-full items-center justify-between gap-2 text-sm font-semibold text-gcego-text">
                                <span>{{ $groupName === '' ? __('Other') : $groupName }}</span>
                                <span class="flex gap-2 text-xs font-normal">
                                    <button type="button" wire:click="toggleGroup(@js($groupName), true)" class="{{ implode(' ', $p::Link) }}">{{ __('All') }}</button>
                                    <button type="button" wire:click="toggleGroup(@js($groupName), false)" class="{{ implode(' ', $p::Link) }}">{{ __('None') }}</button>
                                </span>
                            </legend>
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($items as $permission)
                                    <label class="flex items-start gap-2 text-sm text-gcego-text" wire:key="perm-{{ $permission->id }}">
                                        <input type="checkbox" value="{{ $permission->name }}" wire:model.live="permissions" class="{{ implode(' ', $p::Checkbox) }} mt-0.5">
                                        <span class="min-w-0">
                                            <span class="block">{{ $permission->label }}</span>
                                            <span class="block break-all font-mono text-xs text-gcego-text-muted">{{ $permission->name }}</span>
                                            <span class="mt-0.5 flex flex-wrap gap-1">
                                                @if ($permission->is_sensitive)@include('gcego-vault::partials.badge', ['tone' => 'warning', 'text' => __('Sensitive')])@endif
                                                @isset($inherited[$permission->name])@include('gcego-vault::partials.badge', ['tone' => 'info', 'text' => __('Inherited from :role', ['role' => $inherited[$permission->name]])])@endisset
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @empty
                        <div class="{{ implode(' ', $p::Empty) }}"><p>{{ __('No permission matches the filter.') }}</p></div>
                    @endforelse
                </div>
            </section>
        @endif

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <span x-show="dirty" x-cloak class="self-center text-sm text-gcego-text-muted" role="status">{{ __('Unsaved changes') }}</span>
            <button type="submit" class="{{ $s->button('primary') }}" wire:loading.attr="disabled" wire:target="save">{{ $role === null ? __('Create role') : __('Save role') }}</button>
        </div>
    </form>
</div>
