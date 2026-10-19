<?php

declare(strict_types=1);

namespace Gcego\Forms\Integration;

use Gcego\Actions\Contracts\InputResolver;
use Gcego\Actions\Contracts\RendersInput;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Actions\Execution\ValidatedInput;
use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Forms\Configuration\UsesForm;
use Gcego\Forms\Exceptions\InvalidStateException;
use Gcego\Forms\Form;
use Gcego\Forms\FormManager;
use Gcego\Forms\Rendering\FormRenderContext;
use Gcego\Forms\Rendering\FormRenderFrame;
use Gcego\Forms\State\FormState;
use Gcego\Runtime\Rendering\RenderMode;
use Gcego\Runtime\Runtime;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Records\RecordAuthorizer;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * gcego/actions input from the blueprint's Form, for Create, Edit and
 * actions with UsesForm. The action pipeline stays in charge (resolution,
 * authorization, transactions, persistence); the form contributes validated,
 * authorized data:
 *
 *  - unknown keys (a forged schema or mass assignment) are a 403 denial;
 *  - values are validated with the trusted schema's rules only;
 *  - only visible, editable, submitted fields are returned.
 *
 * It also renders the form inside ActionHost dialogs (RendersInput).
 */
final readonly class FormInputResolver implements InputResolver, RendersInput
{
    public function __construct(private Container $container) {}

    public function supports(ResolvedAction $action): bool
    {
        return self::form($action) !== null && ($action->action::requiresInput() || $action->action->configurations()->has(UsesForm::class));
    }

    public function resolve(ResolvedAction $action, ActionContext $context, array $submitted): ValidatedInput
    {
        $form = self::form($action) ?? throw new \LogicException('The action has no form.');

        try {
            $submission = $this->container->make(FormManager::class)->submission($form, $submitted, $context->record instanceof Model ? $context->record : null, $action->model());
        } catch (InvalidStateException) {
            AuthorizationDecision::deny('fields.write', DenialReason::MissingPermission)->authorize();

            return ValidatedInput::none();
        }

        return new ValidatedInput($submission->data, $submission->afterSave);
    }

    public function initial(ResolvedAction $action, ActionContext $context): array
    {
        $form = self::form($action);

        return $form === null ? [] : $this->container->make(FormManager::class)->hydrate($form, $context->record instanceof Model ? $context->record : null);
    }

    public function renderInput(ResolvedAction $action, string|int|null $record, array $state, array $errors, string $prefix): string
    {
        $form = self::form($action);

        if ($form === null) {
            return '';
        }

        $forms = $this->container->make(FormManager::class);
        $schema = $forms->schema($form);
        $model = $record === null ? null : $this->record($action, $record);
        $frame = new FormRenderFrame($schema, new FormState($state), $forms->access($schema, $model), $prefix, $errors, model: $action->model());

        return $this->container->make(FormRenderContext::class)->run($frame, fn (): string => $this->container->make(Runtime::class)->render($form, RenderMode::Livewire, identity: $action->definition)->toHtml());
    }

    public static function form(ResolvedAction $action): ?Form
    {
        $form = $action->blueprint->part(BlueprintRole::Form);

        return $form instanceof Form ? $form : null;
    }

    private function record(ResolvedAction $action, string|int $key): ?Model
    {
        $source = $action->recordSource();

        if ($source === null) {
            return null;
        }

        [$decision, $record] = $this->container->make(RecordAuthorizer::class)->inspect($source, $key, 'view');

        return $decision->allowed && $record instanceof Model ? $record : null;
    }
}
