<?php

declare(strict_types=1);

namespace Gcego\Forms\Livewire;

use Gcego\Actions\ActionManager;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\Execution\ActionResult;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Actions\Resolution\ActionResolver;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Chroma\ChromaManager;
use Gcego\Components\Evaluation\Computed;
use Gcego\Components\Evaluation\State;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Data\Source;
use Gcego\Forms\Exceptions\InvalidStateException;
use Gcego\Forms\Fields\Field;
use Gcego\Forms\Fields\FileUpload;
use Gcego\Forms\Fields\PasswordInput;
use Gcego\Forms\Fields\Repeater;
use Gcego\Forms\Form;
use Gcego\Forms\FormManager;
use Gcego\Forms\FormSubmitted;
use Gcego\Forms\Presets\FormPresets;
use Gcego\Forms\Rendering\FormRenderContext;
use Gcego\Forms\Rendering\FormRenderFrame;
use Gcego\Forms\Schema\FieldEntry;
use Gcego\Forms\Schema\FormSchema;
use Gcego\Forms\State\FormState;
use Gcego\Forms\State\StatePath;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Rendering\Facades\Rendering;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Records\RecordAuthorizer;
use Gcego\Security\SecurityManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * <livewire:gcego-form definition="subjects" />                    Create (the blueprint's Form)
 * <livewire:gcego-form definition="subjects" :record="$id" />      Edit
 * <livewire:gcego-form definition="subjects" action="reschedule" :record="$id" />
 *
 * Hosts the Form of a registered blueprint. The definition, action, record and
 * wizard step are #[Locked]; $data is untrusted client state, checked against
 * the trusted schema on every update and validated on submit by the action's
 * FormInputResolver. Submitting executes the gcego/actions action, which
 * re-resolves the record (scoped), re-authorizes and persists; the form host
 * itself never writes anything. A blueprint without Create/Edit actions gets a
 * standalone form whose validated data is dispatched as a server-side FormSubmitted event.
 */
final class FormHost extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $definition = '';

    #[Locked]
    public ?string $action = null;

    #[Locked]
    public string|int|null $record = null;

    #[Locked]
    public int $step = 0;

    #[Locked]
    public ?string $confirmation = null;

    /**
     * @var array{status: string, message: string}|null
     */
    #[Locked]
    public ?array $feedback = null;

    /**
     * Untrusted form state.
     *
     * @var array<array-key, mixed>
     */
    public array $data = [];

    /**
     * Values before the current client update, for afterStateUpdated's $old (never sent to the browser).
     *
     * @var array<string, mixed>
     */
    private array $previous = [];

    public function mount(string $definition, ?string $action = null, string|int|null $record = null): void
    {
        [$this->definition, $blueprint] = app(ActionResolver::class)->blueprint($definition);
        $form = $this->form($blueprint);
        app(SecurityManager::class)->definition($blueprint, $this->formPath($blueprint, $this->baseForm($blueprint)))->authorize();

        $this->record = $record;
        $this->action = $action ?? $this->defaultAction($blueprint);

        if ($this->action !== null) {
            $manager = app(ActionManager::class);
            $resolved = $manager->resolve($this->definition, $this->action);

            if (! $manager->allows($resolved, $resolved->action->requiresRecord() === false ? null : $record)) {
                AuthorizationDecision::deny($this->action, DenialReason::PolicyDenied)->authorize();
            }

            $this->data = $manager->initialInput($resolved, $resolved->action->requiresRecord() === false ? null : $record);

            return;
        }

        $this->data = app(FormManager::class)->hydrate($form);
    }

    /**
     * Every client update must address a declared field.
     */
    public function updating(string $property, mixed $value): void
    {
        if ($property === 'data' || str_starts_with($property, 'data.')) {
            $path = $property === 'data' ? '' : substr($property, 5);
            $this->assertDeclared($path);
            $this->previous[$path] = $this->state()->get($path);
        }
    }

    /**
     * Run the updated field's afterStateUpdated closure. It may only set
     * declared fields; everything it sets is validated on submit like any input.
     */
    public function updated(string $property, mixed $value): void
    {
        if (! str_starts_with($property, 'data.')) {
            return;
        }

        $path = substr($property, 5);
        $forms = app(FormManager::class);
        $entry = $this->entryFor($forms->schema($this->form()), $path);
        $hook = $entry?->field->get(Field::AfterStateUpdated);

        if ($entry === null || ! $hook instanceof Computed) {
            return;
        }

        $state = new State($this->data);
        $itemPath = $entry->repeater === null ? null : substr($path, 0, (int) strrpos($path, '.'));
        $recording = $state->record(fn (): mixed => $forms->evaluating($this->model(), fn (): mixed => $forms->evaluation()->evaluate(
            $hook->closure,
            $state,
            $itemPath,
            ['state' => $value, 'old' => $this->previous[$path] ?? null, 'component' => $entry->field],
        ), $this->operation()));

        foreach ($recording['writes'] as $written) {
            $this->assertDeclared($written);
        }

        $this->data = $state->all();
    }

    public function addItem(string $path): void
    {
        $repeater = $this->repeaterAt($path);
        $items = $this->state()->get($path);
        $items = is_array($items) ? $items : [];

        if ($repeater->maxItems() !== null && count($items) >= $repeater->maxItems()) {
            return;
        }

        $items[StatePath::newItemKey()] = [];
        $this->data = $this->state()->with($path, $items)->all();
    }

    public function removeItem(string $path, string $key): void
    {
        $repeater = $this->repeaterAt($path);
        $items = $this->state()->get($path);

        if (! is_array($items) || ! array_key_exists($key, $items) || count($items) <= $repeater->minItems()) {
            return;
        }

        unset($items[$key]);
        $this->data = $this->state()->with($path, $items)->all();
    }

    public function moveItem(string $path, string $key, int $offset): void
    {
        $repeater = $this->repeaterAt($path);
        $items = $this->state()->get($path);

        if (! $repeater->isReorderable() || ! is_array($items) || ! array_key_exists($key, $items) || ! in_array($offset, [-1, 1], true)) {
            return;
        }

        $keys = array_keys($items);
        $index = (int) array_search($key, $keys, true);
        $target = $index + $offset;

        if ($target < 0 || $target >= count($keys)) {
            return;
        }

        [$keys[$index], $keys[$target]] = [$keys[$target], $keys[$index]];
        $this->data = $this->state()->with($path, array_combine($keys, array_map(static fn (string|int $item): mixed => $items[$item], $keys)))->all();
    }

    public function nextStep(): void
    {
        $form = $this->form();
        $schema = app(FormManager::class)->schema($form);
        $this->resetErrorBag();

        try {
            app(FormManager::class)->validateStep($form, $this->data, $this->step, $this->model(), $this->sourceModel());
        } catch (ValidationException $exception) {
            $this->showErrors($exception->errors(), $schema);

            return;
        }

        $this->step = min($this->step + 1, max(0, $schema->stepCount() - 1));
    }

    public function previousStep(): void
    {
        $this->step = max(0, $this->step - 1);
    }

    public function submit(): void
    {
        $this->resetErrorBag();
        $this->feedback = null;
        $form = $this->form();
        $schema = app(FormManager::class)->schema($form);

        if ($this->action === null) {
            try {
                $data = app(FormManager::class)->process($form, $this->data, null, $this->sourceModel());
            } catch (ValidationException $exception) {
                $this->showErrors($exception->errors(), $schema);

                return;
            }

            event(new FormSubmitted($this->definition, $form->formName(), $data, auth()->id()));
            $this->feedback = ['status' => 'success', 'message' => (string) __('gcego-forms::messages.submitted')];
            $this->dispatch('gcego-form-submitted', definition: $this->definition);

            return;
        }

        $resolved = app(ActionManager::class)->resolve($this->definition, $this->action);
        $result = app(ActionManager::class)->execute(new ActionRequest($this->definition, $this->action, $resolved->action->requiresRecord() === false ? null : $this->record, input: $this->data, confirmation: $this->confirmation));
        $this->confirmation = null;
        $this->handle($result, $schema);
    }

    public function render(): View
    {
        $form = $this->form();
        $forms = app(FormManager::class);
        $schema = $forms->schema($form);
        $model = $this->model();
        $errors = [];
        $summary = [];

        $bag = $this->getErrorBag();

        foreach ($bag instanceof MessageBag ? $bag->toArray() : [] as $key => $messages) {
            $errors[(string) preg_replace('/^data\./', '', $key)] = array_values(array_filter((array) $messages, is_string(...)));
        }

        $state = new FormState($this->data);
        $live = $forms->evaluating($model, fn (): array => $forms->livePaths($schema, $state), $this->operation());
        $frame = new FormRenderFrame($schema, $state, $forms->access($schema, $model), 'data', $errors, step: $this->step, model: $this->sourceModel(), idPrefix: is_string($id = $this->getId()) ? $id : null, live: $live);
        $content = $forms->evaluating($model, fn (): string => app(FormRenderContext::class)->run($frame, fn (): string => Rendering::render($form, RenderMode::Livewire, identity: $this->definition)->toHtml()), $this->operation());

        foreach ($errors as $path => $messages) {
            $entry = $this->entryFor($schema, $path);
            $summary[] = ['id' => $frame->idFor($path), 'label' => $entry === null ? $path : (string) __($entry->field->label()), 'message' => $messages[0] ?? ''];
        }

        $styling = app(ChromaManager::class);
        $submit = $form->get(Form::SubmitLabel);

        return view('gcego-forms::forms.host', [
            'content' => new HtmlString($content),
            'errorSummary' => $summary,
            'summaryClass' => implode(' ', FormPresets::Summary),
            'showSubmit' => $schema->stepCount() === 0 || $this->step >= $schema->stepCount() - 1,
            'submitLabel' => is_string($submit) ? (string) __($submit) : (string) __('gcego-forms::messages.submit'),
            'submitClass' => $styling->merge($styling->preset('control.button'), $styling->preset('control.button.primary'), FormPresets::PrimaryButton),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $errors
     */
    private function showErrors(array $errors, FormSchema $schema): void
    {
        $firstStep = null;

        foreach ($errors as $path => $messages) {
            foreach (is_array($messages) ? $messages : [$messages] as $message) {
                if (is_string($message)) {
                    $this->addError('data.'.$path, $message);
                }
            }

            $step = $this->entryFor($schema, (string) $path)?->step;
            $firstStep = $firstStep === null || ($step !== null && $step < $firstStep) ? $step : $firstStep;
        }

        if ($firstStep !== null && $firstStep < $this->step) {
            $this->step = $firstStep;
        }
    }

    private function handle(ActionResult $result, FormSchema $schema): void
    {
        if ($result->status === ActionStatus::ValidationFailed) {
            $this->showErrors($result->errors, $schema);
            $this->feedback = ['status' => $result->status->value, 'message' => $result->message];

            return;
        }

        if ($result->status === ActionStatus::ConfirmationRequired) {
            $this->confirmation = $result->confirmation['token'] ?? null;
            $this->feedback = ['status' => $result->status->value, 'message' => $result->message];

            return;
        }

        $this->feedback = ['status' => $result->status->value, 'message' => $result->message];

        if ($result->successful()) {
            $this->clearSecrets($schema);

            if ($this->record === null) {
                $this->data = app(FormManager::class)->hydrate($this->form());
                $this->step = 0;
            }

            $this->dispatch('gcego-action-completed', result: $result->toArray());

            if ($result->redirect !== null) {
                $this->redirect($result->redirect);
            }
        }
    }

    /**
     * Passwords and uploads never stay in client state after a submission.
     */
    private function clearSecrets(FormSchema $schema): void
    {
        foreach ($schema->children() as $entry) {
            if ($entry->field instanceof PasswordInput) {
                $this->data[$entry->name()] = null;
                unset($this->data[$entry->name().'_confirmation']);
            }

            if ($entry->field instanceof FileUpload && ! is_string($this->data[$entry->name()] ?? null)) {
                $this->data[$entry->name()] = null;
            }
        }
    }

    private function assertDeclared(string $path): void
    {
        if ($path === '') {
            throw InvalidStateException::unknownPath('data');
        }

        $schema = app(FormManager::class)->schema($this->form());
        $template = (string) preg_replace('/\.i[a-f0-9]{10}(?=\.|$)/', '.*', $path);
        $segments = explode('.', $path);

        foreach ($segments as $segment) {
            if (! preg_match(StatePath::NamePattern, $segment) && ! StatePath::isItemKey($segment)) {
                throw InvalidStateException::unknownPath($path);
            }
        }

        $base = (string) preg_replace('/_confirmation$/', '', $template);

        if ($schema->entry($template) === null && ! ($schema->entry($base)?->field instanceof PasswordInput)) {
            // Repeater containers ("objectives" or "objectives.i…") and file-upload array slots are declared too.
            $parent = (string) preg_replace('/\.(\*|\d+)$/', '', $template);

            if (! ($schema->entry($parent)?->field instanceof Repeater) && ! ($schema->entry($parent)?->field instanceof FileUpload)) {
                throw InvalidStateException::unknownPath($path);
            }
        }
    }

    private function repeaterAt(string $path): Repeater
    {
        $this->assertDeclared($path);
        $entry = app(FormManager::class)->schema($this->form())->entry((string) preg_replace('/\.i[a-f0-9]{10}(?=\.|$)/', '.*', $path));

        return $entry?->field instanceof Repeater ? $entry->field : throw InvalidStateException::unknownPath($path);
    }

    private function entryFor(FormSchema $schema, string $path): ?FieldEntry
    {
        return $schema->entry((string) preg_replace('/\.i[a-f0-9]{10}(?=\.|$)/', '.*', $path));
    }

    private function state(): FormState
    {
        return new FormState($this->data);
    }

    private function blueprint(): BlueprintInterface
    {
        return app(ActionResolver::class)->blueprint($this->definition)[1];
    }

    /**
     * The blueprint's form with the extensions registered for "{blueprint name}.form".
     */
    private function form(?BlueprintInterface $blueprint = null): Form
    {
        $blueprint ??= $this->blueprint();

        return app(FormManager::class)->extended($this->baseForm($blueprint), $blueprint->name() ?? $this->definition);
    }

    private function baseForm(BlueprintInterface $blueprint): Form
    {
        $form = $blueprint->part(BlueprintRole::Form);

        return $form instanceof Form ? $form : abort(404);
    }

    /**
     * The $operation form closures receive: the action when it is create, edit or view.
     */
    private function operation(): ?string
    {
        return in_array($this->action, ['create', 'edit', 'view'], true) ? $this->action : null;
    }

    private function formPath(BlueprintInterface $blueprint, Form $form): string
    {
        foreach (app(ComponentWalker::class)->preOrder($blueprint) as $node) {
            if ($node->component === $form) {
                return $node->path->toString();
            }
        }

        return $blueprint->name() ?? '';
    }

    private function defaultAction(BlueprintInterface $blueprint): ?string
    {
        $names = array_map(static fn ($resolved): string => $resolved->name(), app(ActionManager::class)->actions($this->definition));
        $wanted = $this->record === null ? 'create' : 'edit';

        return in_array($wanted, $names, true) ? $wanted : null;
    }

    /**
     * @return class-string<Model>|null
     */
    private function sourceModel(): ?string
    {
        $source = $this->blueprint()->part(BlueprintRole::Source);
        $model = $source instanceof Source ? $source->get(Source::Model) : null;

        return is_string($model) && is_subclass_of($model, Model::class) ? $model : null;
    }

    private function model(): ?Model
    {
        if ($this->record === null) {
            return null;
        }

        $source = $this->blueprint()->part(BlueprintRole::Source);

        if (! $source instanceof Source) {
            return null;
        }

        [$decision, $record] = app(RecordAuthorizer::class)->inspect($source, $this->record, 'view');

        return $decision->allowed && $record instanceof Model ? $record : null;
    }
}
