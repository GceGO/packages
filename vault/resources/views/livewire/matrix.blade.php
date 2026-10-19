{{-- gcego-vault::livewire.matrix — the permission matrix of one role. --}}
<div class="{{ implode(' ', $p::Screen) }}" data-vault-screen="matrix" data-dirty="{{ count($added) + count($removed) }}"
     x-data x-init="window.addEventListener('beforeunload', (event) => { if ($el.dataset.dirty !== '0') { event.preventDefault(); event.returnValue = ''; } })">
    <x-gcego-vault::header :title="__('Permission matrix')" :lead="__('Tick what a role may do. Changes are a draft until you save them.')" :scope="$scopeLabel" />

    @include('gcego-vault::partials.feedback')

    <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }} flex flex-col gap-4 lg:flex-row lg:items-end" aria-label="{{ __('Choose a role') }}">
        <div class="flex-1">
            <label for="vault-matrix-role" class="{{ implode(' ', $p::Label) }}">{{ __('Role') }}</label>
            <select id="vault-matrix-role" wire:model.live="roleId" class="{{ $s->input() }}">
                @foreach ($roles as $option)<option value="{{ $option->id }}">{{ $option->label }} ({{ $option->name }})</option>@endforeach
            </select>
        </div>
        <div class="flex-1">
            <label for="vault-matrix-search" class="{{ implode(' ', $p::Label) }}">{{ __('Filter') }}</label>
            <input id="vault-matrix-search" type="search" wire:model.live.debounce.300ms="search" maxlength="100" placeholder="{{ __('Resource, permission or group') }}" class="{{ $s->input() }}">
        </div>
        <fieldset class="flex-1">
            <legend class="{{ implode(' ', $p::Label) }}">{{ __('Compare with (up to 4)') }}</legend>
            <div class="flex max-h-24 flex-wrap gap-x-4 gap-y-1 overflow-y-auto">
                @foreach ($roles as $option)
                    @if ($option->id !== $role?->id)
                        <label class="inline-flex items-center gap-1 text-sm text-gcego-text"><input type="checkbox" value="{{ $option->id }}" wire:model.live="compare" class="{{ implode(' ', $p::Checkbox) }}"> {{ $option->label }}</label>
                    @endif
                @endforeach
            </div>
        </fieldset>
    </section>

    @if ($role === null)
        <div class="{{ implode(' ', [...$p::Card, ...$p::Empty]) }}"><p>{{ __('There is no role here yet.') }}</p></div>
    @else
        @if ($locked)<p class="{{ implode(' ', [...$p::Notice, ...$p::NoticeTones['info']]) }}">{{ __('This role is managed by the application configuration and is read-only here.') }}</p>@endif
        @if ($role->is_super)<p class="{{ implode(' ', [...$p::Notice, ...$p::NoticeTones['info']]) }}">{{ __('Super-administrator roles get every global permission by configuration; their matrix is read-only.') }}</p>@endif

        @php($editable = $canEdit && ! $locked)
        <section class="{{ implode(' ', $p::Card) }}" aria-label="{{ __('Permissions of :role', ['role' => $role->label]) }}">
            <div class="overflow-x-auto">
                <table class="{{ implode(' ', $p::Table) }}">
                    <thead>
                        <tr>
                            <th scope="col" class="{{ implode(' ', [...$p::Th, ...$p::Sticky]) }}">{{ __('Resource') }}</th>
                            @foreach ($abilities as $ability)
                                <th scope="col" class="{{ implode(' ', [...$p::Th, ...$p::MatrixCell]) }}">
                                    <span class="block normal-case">{{ \Gcego\Vault\Support\Names::label($ability) }}</span>
                                    @if ($editable)
                                        <span class="mt-1 flex justify-center gap-1 text-[10px] normal-case">
                                            <button type="button" wire:click="setColumn('{{ $ability }}', true)" class="{{ implode(' ', $p::Link) }}" aria-label="{{ __('Allow :ability for every resource shown', ['ability' => $ability]) }}">{{ __('all') }}</button>
                                            <button type="button" wire:click="setColumn('{{ $ability }}', false)" class="{{ implode(' ', $p::Link) }}" aria-label="{{ __('Remove :ability from every resource shown', ['ability' => $ability]) }}">{{ __('none') }}</button>
                                        </span>
                                    @endif
                                </th>
                            @endforeach
                            <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Other') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gcego-border">
                        @php($group = false)
                        @forelse ($rows as $resource => $row)
                            @if ($group !== $row['group'])
                                @php($group = $row['group'])
                                <tr><th colspan="{{ count($abilities) + 2 }}" scope="colgroup" class="bg-gcego-surface-muted px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gcego-text-muted">{{ $group ?? __('Other') }}</th></tr>
                            @endif
                            <tr class="{{ implode(' ', $p::Row) }}" wire:key="row-{{ md5($resource) }}" data-vault-row="{{ $resource }}">
                                <th scope="row" class="{{ implode(' ', [...$p::Td, ...$p::Sticky]) }} font-medium">
                                    {{ \Gcego\Vault\Support\Names::label($resource) }}
                                    @if ($editable)
                                        <span class="ml-1 text-xs font-normal">
                                            <button type="button" wire:click="setRow(@js($resource), true)" class="{{ implode(' ', $p::Link) }}">{{ __('all') }}</button> ·
                                            <button type="button" wire:click="setRow(@js($resource), false)" class="{{ implode(' ', $p::Link) }}">{{ __('none') }}</button>
                                        </span>
                                    @endif
                                </th>
                                @foreach ($abilities as $ability)
                                    @php($cell = $row['cells'][$ability] ?? null)
                                    <td class="{{ implode(' ', $p::MatrixCell) }} {{ $cell !== null && (in_array($cell->name, $added, true) || in_array($cell->name, $removed, true)) ? implode(' ', $p::Changed) : '' }}">
                                        @if ($cell === null)
                                            <span class="text-gcego-text-muted" aria-hidden="true">·</span>
                                        @elseif (isset($inherited[$cell->id]) && ! ($draft[$cell->id] ?? false))
                                            <span title="{{ __('Inherited from :role', ['role' => $inherited[$cell->id]]) }}" class="text-gcego-info">✓<span class="sr-only">{{ __('Inherited from :role', ['role' => $inherited[$cell->id]]) }}</span></span>
                                        @else
                                            <input type="checkbox" wire:model.live="draft.{{ $cell->id }}" @disabled(! $editable) class="{{ implode(' ', $p::Checkbox) }}" aria-label="{{ $cell->label }}" data-vault-cell="{{ $cell->name }}">
                                            @if ($cell->is_sensitive)<span class="sr-only">{{ __('(sensitive)') }}</span><span aria-hidden="true" class="ml-0.5 text-xs text-gcego-warning">!</span>@endif
                                        @endif
                                    </td>
                                @endforeach
                                <td class="{{ implode(' ', $p::Td) }}">
                                    <div class="flex flex-col gap-1">
                                        @foreach ($row['other'] as $permission)
                                            <label class="inline-flex items-center gap-2 text-xs {{ in_array($permission->name, $added, true) || in_array($permission->name, $removed, true) ? implode(' ', $p::Changed) : '' }}">
                                                @if (isset($inherited[$permission->id]) && ! ($draft[$permission->id] ?? false))
                                                    <span class="text-gcego-info">✓</span>
                                                @else
                                                    <input type="checkbox" wire:model.live="draft.{{ $permission->id }}" @disabled(! $editable) class="{{ implode(' ', $p::Checkbox) }}" data-vault-cell="{{ $permission->name }}">
                                                @endif
                                                <span class="font-mono">{{ \Illuminate\Support\Str::after($permission->name, $resource.'.') }}</span>
                                                @if ($permission->is_sensitive)<span class="text-gcego-warning">({{ __('sensitive') }})</span>@endif
                                            </label>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($abilities) + 2 }}" class="{{ implode(' ', $p::Empty) }}">{{ __('No permission matches the filter.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }} lg:col-span-1" aria-labelledby="vault-matrix-changes" aria-live="polite">
                <h2 id="vault-matrix-changes" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Unsaved changes') }}</h2>
                @if ($added === [] && $removed === [])
                    <p class="mt-2 text-sm text-gcego-text-muted">{{ __('None.') }}</p>
                @else
                    <ul class="mt-2 space-y-1 font-mono text-xs">
                        @foreach ($added as $name)<li class="text-gcego-success">+ {{ $name }}</li>@endforeach
                        @foreach ($removed as $name)<li class="text-gcego-danger">− {{ $name }}</li>@endforeach
                    </ul>
                    @if ($missing !== [])<p class="mt-3 text-sm text-gcego-danger">{{ __('Also needed: :permissions', ['permissions' => implode(', ', $missing)]) }}</p>@endif
                    @if ($editable)
                        <div class="mt-4 flex gap-2">
                            <button type="button" wire:click="discard" class="{{ $s->button('secondary') }}">{{ __('Discard') }}</button>
                            <button type="button" wire:click="save" class="{{ $s->button('primary') }}" wire:loading.attr="disabled" wire:target="save">{{ __('Save changes') }}</button>
                        </div>
                    @endif
                @endif
            </section>
            <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }} lg:col-span-2" aria-labelledby="vault-matrix-effective">
                <h2 id="vault-matrix-effective" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Effective permissions after saving') }} <span class="text-sm font-normal text-gcego-text-muted">({{ $effective->count() }})</span></h2>
                <p class="mt-1 text-xs text-gcego-text-muted">{{ __('Ticked here plus everything inherited.') }}</p>
                <p class="mt-2 break-words font-mono text-xs leading-relaxed text-gcego-text">{{ $effective->implode(' · ') ?: '—' }}</p>
            </section>
        </div>

        @if ($comparison !== [])
            <section class="{{ implode(' ', $p::Card) }}" aria-labelledby="vault-matrix-compare">
                <div class="{{ implode(' ', $p::Toolbar) }}"><h2 id="vault-matrix-compare" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Comparison (effective, with inheritance)') }}</h2></div>
                @php($all = $effective->merge(collect($comparison)->flatMap(static fn ($set) => array_keys($set)))->unique()->sort()->values())
                <div class="overflow-x-auto">
                    <table class="{{ implode(' ', $p::Table) }}">
                        <thead><tr>
                            <th scope="col" class="{{ implode(' ', [...$p::Th, ...$p::Sticky]) }}">{{ __('Permission') }}</th>
                            <th scope="col" class="{{ implode(' ', [...$p::Th, ...$p::MatrixCell]) }}">{{ $role->label }}</th>
                            @foreach ($comparison as $label => $set)<th scope="col" class="{{ implode(' ', [...$p::Th, ...$p::MatrixCell]) }}">{{ $label }}</th>@endforeach
                        </tr></thead>
                        <tbody class="divide-y divide-gcego-border">
                            @foreach ($all as $name)
                                <tr>
                                    <th scope="row" class="{{ implode(' ', [...$p::Td, ...$p::Sticky]) }} font-mono text-xs font-normal">{{ $name }}</th>
                                    <td class="{{ implode(' ', $p::MatrixCell) }}">{!! $effective->contains($name) ? '✓<span class="sr-only">'.e(__('yes')).'</span>' : '<span class="text-gcego-text-muted">—</span><span class="sr-only">'.e(__('no')).'</span>' !!}</td>
                                    @foreach ($comparison as $set)<td class="{{ implode(' ', $p::MatrixCell) }}">{!! isset($set[$name]) ? '✓<span class="sr-only">'.e(__('yes')).'</span>' : '<span class="text-gcego-text-muted">—</span><span class="sr-only">'.e(__('no')).'</span>' !!}</td>@endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif

    <x-gcego-vault::confirm :show="$confirmingSensitive" :title="__('Add sensitive permissions?')" :confirm="__('Save anyway')" action="save">
        <p>{{ __('These high-risk permissions will be given to everyone holding ":role":', ['role' => $role?->label ?? '']) }}</p>
        <ul class="mt-2 list-disc pl-5">@foreach ($sensitiveAdded as $label)<li>{{ $label }}</li>@endforeach</ul>
    </x-gcego-vault::confirm>
</div>
