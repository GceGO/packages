<?php

declare(strict_types=1);

namespace Gcego\Actions\Livewire;

use Gcego\Actions\Action;
use Gcego\Actions\ActionManager;
use Gcego\Actions\Configuration\Input;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\Execution\ActionResult;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Actions\Resolution\ActionResolver;
use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Chroma\ChromaManager;
use Gcego\Luminaries\Luminaries;
use Illuminate\Contracts\View\View;
use Illuminate\Support\MessageBag;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * <livewire:gcego-actions definition="blueprint:subjects" placement="row" :record="$subject->id" />
 *
 * Renders the actions of one registered blueprint (optionally one placement,
 * names and record) and executes them through ActionManager.
 *
 * Trust: the definition, scope, record and dialog are #[Locked] and set by
 * the server; only $input is client state, and it is untrusted (validated by
 * the action's InputResolver). Every request re-resolves the definition, the
 * record (scoped) and the authorization with the current user and tenant;
 * nothing authorized earlier is reused. Handlers and definitions never reach
 * the browser. A confirmation is a signed, single-use token issued by the
 * server, so a forged, replayed or transplanted confirmation is refused.
 */
final class ActionHost extends Component
{
    #[Locked]
    public string $definition = '';

    /**
     * @var list<string>
     */
    #[Locked]
    public array $names = [];

    #[Locked]
    public ?string $placement = null;

    #[Locked]
    public string|int|null $record = null;

    /**
     * Set by a parent (a table) that sends gcego-actions-run requests to this
     * host; requests for another channel are ignored.
     */
    #[Locked]
    public ?string $channel = null;

    /**
     * False for a host that only runs requested actions and shows dialogs.
     */
    #[Locked]
    public bool $showButtons = true;

    /**
     * @var array{action: string, mode: string, token: string|null, title: string, message: string, confirm: string, cancel: string, destructive: bool, fields: list<array{name: string, label: string}>, selection: list<string|int>}|null
     */
    #[Locked]
    public ?array $dialog = null;

    /**
     * @var array{status: string, message: string}|null
     */
    #[Locked]
    public ?array $feedback = null;

    /**
     * Untrusted values for the open dialog's fields.
     *
     * @var array<string, mixed>
     */
    public array $input = [];

    /**
     * @param  list<string>  $actions
     */
    public function mount(string $definition, array $actions = [], ?string $placement = null, string|int|null $record = null, ?string $channel = null, bool $showButtons = true): void
    {
        $this->definition = app(ActionResolver::class)->blueprint($definition)[0];
        $this->names = array_values(array_filter($actions, is_string(...)));
        $this->placement = $placement;
        $this->record = $record;
        $this->channel = $channel;
        $this->showButtons = $showButtons;
    }

    /**
     * A row or bulk action requested by the parent on this host's channel
     * (gcego/tables). The record key and selection are untrusted: run() and
     * runBulk() re-resolve them through the scoped source and re-authorize.
     *
     * @param  array<array-key, mixed>  $keys
     */
    #[On('gcego-actions-run')]
    public function runRequested(string $channel, string $action, string|int|null $record = null, array $keys = []): void
    {
        if ($this->channel === null || ! hash_equals($this->channel, $channel)) {
            return;
        }

        if ($keys !== []) {
            $this->runBulk($action, $keys);

            return;
        }

        if ($record === null || (is_string($record) && (strlen($record) > 255 || $record === ''))) {
            return;
        }

        $this->dialog = null;
        $this->record = $record;
        $this->run($action);
    }

    public function run(string $action): void
    {
        $this->feedback = null;
        $resolved = $this->scoped($action);

        if ($resolved === null) {
            return;
        }

        if ($resolved->isBulk()) {
            $this->feedback = ['status' => ActionStatus::Failed->value, 'message' => (string) __('gcego-actions::messages.invalid_request')];

            return;
        }

        $manager = app(ActionManager::class);

        if ($manager->takesInput($resolved)) {
            if (! $manager->allows($resolved, $this->record)) {
                $this->show(ActionResult::denied($resolved->identity(), (string) __('gcego-actions::messages.denied')));

                return;
            }

            $this->resetErrorBag();
            $this->input = $manager->initialInput($resolved, $this->record);
            $this->dialog = $this->dialogFor($resolved, 'input');

            return;
        }

        $this->handle($resolved, $manager->execute(new ActionRequest($this->definition, $resolved->name(), $this->record)));
    }

    /**
     * Start a bulk action for keys selected in the browser (untrusted: re-resolved through scoped data).
     *
     * @param  array<array-key, mixed>  $keys
     */
    public function runBulk(string $action, array $keys): void
    {
        $this->feedback = null;
        $resolved = $this->scoped($action);

        if ($resolved === null || ! $resolved->isBulk()) {
            $this->show(ActionResult::notFound($action, (string) __('gcego-actions::messages.not_found')));

            return;
        }

        $selection = array_values(array_filter($keys, static fn (mixed $key): bool => is_int($key) || is_string($key)));
        $result = app(ActionManager::class)->execute(new ActionRequest($this->definition, $resolved->name(), selection: $selection));
        $this->handle($resolved, $result, $selection);
    }

    public function submit(): void
    {
        if ($this->dialog === null) {
            return;
        }

        $resolved = $this->scoped($this->dialog['action']);
        $this->resetErrorBag();

        if ($resolved === null) {
            $this->dialog = null;

            return;
        }

        $request = new ActionRequest(
            $this->definition,
            $resolved->name(),
            $resolved->isBulk() ? null : $this->record,
            $this->dialog['selection'],
            $this->dialog['mode'] === 'input' || $this->dialog['fields'] !== [] ? $this->input : [],
            $this->dialog['token'],
        );

        $this->handle($resolved, app(ActionManager::class)->execute($request), $this->dialog['selection']);
    }

    public function cancel(): void
    {
        $this->dialog = null;
        $this->input = [];
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $manager = app(ActionManager::class);
        $styling = app(ChromaManager::class);
        $icons = app(Luminaries::class);
        $buttons = [];

        foreach ($this->showButtons ? $manager->available($this->definition, $this->placement, $this->record, $this->names) : [] as $resolved) {
            $action = $resolved->action;
            $buttons[] = [
                'name' => $resolved->name(),
                'label' => (string) __($action->label()),
                'icon' => $icons->svg(is_string($action->get(Action::Icon)) ? $action->get(Action::Icon) : null, 'size-4'),
                'bulk' => $resolved->isBulk(),
                'disabled' => $action->isDisabled(),
                'class' => $styling->merge($styling->classes($action, $action->intent()->value), $styling->classes($action, 'root')),
            ];
        }

        $dialogForm = null;

        if ($this->dialog !== null && $this->dialog['mode'] === 'input') {
            try {
                $errors = [];
                $bag = $this->getErrorBag();

                foreach ($bag instanceof MessageBag ? $bag->toArray() : [] as $key => $messages) {
                    $errors[(string) preg_replace('/^input\./', '', $key)] = array_values(array_filter((array) $messages, is_string(...)));
                }

                $dialogForm = $manager->renderInput($manager->resolve($this->definition, $this->dialog['action']), $this->record, $this->input, $errors);
            } catch (\Throwable) {
                $dialogForm = null;
            }
        }

        return view('gcego-actions::host', [
            'buttons' => $buttons,
            'dialogForm' => $dialogForm,
            'dialogClass' => $styling->preset('surface.panel')->toString(),
        ]);
    }

    /**
     * @param  list<string|int>  $selection
     */
    private function handle(ResolvedAction $resolved, ActionResult $result, array $selection = []): void
    {
        match ($result->status) {
            ActionStatus::ConfirmationRequired => $this->dialog = $this->dialogFor($resolved, 'confirm', $result->confirmation, $selection),
            ActionStatus::ValidationFailed => $this->errors($result),
            default => $this->show($result),
        };
    }

    private function show(ActionResult $result): void
    {
        if ($result->closeDialog || ! $result->successful()) {
            $this->dialog = null;
            $this->input = [];
        }

        $this->feedback = ['status' => $result->status->value, 'message' => $result->message];
        $this->dispatch('gcego-action-completed', result: $result->toArray());

        if ($result->successful() && $result->redirect !== null) {
            $this->redirect($result->redirect);
        }
    }

    private function errors(ActionResult $result): void
    {
        $this->resetErrorBag();

        foreach ($result->errors as $field => $messages) {
            foreach ($messages as $message) {
                $this->addError('input.'.$field, $message);
            }
        }

        $this->feedback = ['status' => $result->status->value, 'message' => $result->message];
    }

    /**
     * @param  array{token: string, title: string, message: string, confirm: string, cancel: string, destructive: bool}|null  $confirmation
     * @param  list<string|int>  $selection
     * @return array{action: string, mode: string, token: string|null, title: string, message: string, confirm: string, cancel: string, destructive: bool, fields: list<array{name: string, label: string}>, selection: list<string|int>}
     */
    private function dialogFor(ResolvedAction $resolved, string $mode, ?array $confirmation = null, array $selection = []): array
    {
        $input = $resolved->action->configurations()->first(Input::class);
        $fields = $input instanceof Input ? array_map(static fn (string $field): array => ['name' => $field, 'label' => (string) __($input->label($field))], $input->fields()) : [];

        return [
            'action' => $resolved->name(),
            'mode' => $mode,
            'token' => $confirmation['token'] ?? null,
            'title' => $confirmation['title'] ?? (string) __($resolved->action->label()),
            'message' => $confirmation['message'] ?? '',
            'confirm' => $confirmation['confirm'] ?? (string) __($resolved->action->label()),
            'cancel' => $confirmation['cancel'] ?? (string) __('gcego-actions::messages.cancel'),
            'destructive' => $confirmation['destructive'] ?? false,
            'fields' => $mode === 'input' || ($this->dialog['mode'] ?? null) === 'input' ? $fields : [],
            'selection' => $selection,
        ];
    }

    /**
     * The action, only when it belongs to this host's definition and scope.
     */
    private function scoped(string $action): ?ResolvedAction
    {
        try {
            $resolved = app(ActionManager::class)->resolve($this->definition, $action);
        } catch (\Throwable) {
            $resolved = null;
        }

        if ($resolved === null || ($this->names !== [] && ! in_array($resolved->name(), $this->names, true)) || ($this->placement !== null && ! in_array($this->placement, $resolved->action->placements(), true))) {
            $this->feedback = ['status' => ActionStatus::NotFound->value, 'message' => (string) __('gcego-actions::messages.not_found')];

            return null;
        }

        return $resolved;
    }
}
