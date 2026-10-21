<?php

declare(strict_types=1);

namespace Gcego\Forms;

use Closure;
use Gcego\Components\Facades\Components;
use Gcego\Forms\Diagnostics\FormInspector;
use Gcego\Forms\Diagnostics\FormReport;
use Gcego\Forms\Exceptions\InvalidFieldException;
use Gcego\Forms\Exceptions\InvalidStateException;
use Gcego\Forms\Fields\ChoiceField;
use Gcego\Forms\Fields\Field;
use Gcego\Forms\Fields\Hidden;
use Gcego\Forms\Fields\PasswordInput;
use Gcego\Forms\Fields\Repeater;
use Gcego\Forms\Options\OptionCollection;
use Gcego\Forms\Options\OptionsResolver;
use Gcego\Forms\Reactive\ConditionEvaluator;
use Gcego\Forms\Reactive\FormEvaluation;
use Gcego\Forms\Schema\FormSchema;
use Gcego\Forms\Schema\SchemaCompiler;
use Gcego\Forms\State\FieldAccess;
use Gcego\Forms\State\FormState;
use Gcego\Forms\State\StateDehydrator;
use Gcego\Forms\State\StateHydrator;
use Gcego\Forms\State\StatePath;
use Gcego\Forms\State\Submission;
use Gcego\Forms\Validation\ValidationEngine;
use Gcego\Security\SecurityManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use WeakMap;

/**
 * The entry point of gcego/forms (no facade).
 *
 *     $forms = app(FormManager::class);
 *
 *     $state = $forms->hydrate($form, $subject);                 // what the browser may see
 *     $data = $forms->process($form, $request->all(), $subject); // validated, authorized, ready to persist
 *
 * process(): unknown keys are refused, submitted values are normalized and
 * validated with trusted rules, and only visible, editable, submitted fields
 * are returned. It never persists: gcego/actions (or your trusted code) does.
 */
final class FormManager
{
    /**
     * @var WeakMap<Form, FormSchema>
     */
    private WeakMap $schemas;

    /**
     * @var WeakMap<Form, array<string, Form>>
     */
    private WeakMap $extended;

    public function __construct(private readonly Container $container, private readonly FormsConfig $config)
    {
        $this->schemas = new WeakMap;
        $this->extended = new WeakMap;
    }

    /**
     * The blueprint's form with every Components::extend() registered for
     * "{blueprint name}.form" applied, so modules can add, change or remove
     * fields of another module's form:
     *
     *     Components::extend('subjects.form', fn (SchemaPatch $patch) => $patch
     *         ->insertAfter('code', TextInput::make('short_code')));
     *
     * The result is cached per form instance, so every lifecycle (render,
     * update, submit) works on the same patched form.
     */
    public function extended(Form $form, string $blueprint): Form
    {
        if (isset($this->extended[$form][$blueprint])) {
            return $this->extended[$form][$blueprint];
        }

        $patched = Components::extended($blueprint.'.form', $form);

        if (! $patched instanceof Form) {
            throw InvalidFieldException::invalid('Form', sprintf('extensions of "%s.form" must keep the form as the root.', $blueprint));
        }

        $cache = $this->extended[$form] ?? [];
        $cache[$blueprint] = $patched;
        $this->extended[$form] = $cache;

        return $patched;
    }

    /**
     * Field paths rendered live for this state: those conditions declare, plus
     * those closures read while the conditions are evaluated.
     *
     * @return list<string>
     */
    public function livePaths(FormSchema $schema, FormState $state): array
    {
        $conditions = $this->conditions();
        $discovered = $this->evaluation()->discover(static function () use ($conditions, $schema, $state): void {
            foreach ($conditions->instances($schema, $state, visibleOnly: false) as $instance) {
                if ($conditions->visible($instance, $state)) {
                    $conditions->required($instance, $state);
                    $conditions->disabled($instance, $state);
                }
            }
        });

        return array_values(array_unique([...$schema->live, ...array_filter($discovered, static fn (string $path): bool => $schema->entry($path) !== null)]));
    }

    /**
     * Run the callback with $record and $operation available to form closures.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function evaluating(?Model $record, Closure $callback, ?string $operation = null): mixed
    {
        return $this->evaluation()->within(['record' => $record, 'operation' => $operation ?? ($record === null ? 'create' : 'edit')], $callback);
    }

    public function evaluation(): FormEvaluation
    {
        return $this->container->make(FormEvaluation::class);
    }

    public function schema(Form $form): FormSchema
    {
        return $this->schemas[$form] ??= (new SchemaCompiler)->compile($form);
    }

    /**
     * @return array<string, mixed>
     */
    public function hydrate(Form $form, ?Model $record = null): array
    {
        $schema = $this->schema($form);

        return $this->evaluating($record, fn (): array => $this->container->make(StateHydrator::class)->hydrate($schema, $this->access($schema, $record), $record));
    }

    /**
     * Normalize, validate and dehydrate a submission.
     *
     * @param  array<array-key, mixed>  $submitted  Untrusted values.
     * @param  class-string<Model>|null  $model  The form's model, for relationship fields (defaults to the record's class).
     * @return array<string, mixed>
     *
     * @throws ValidationException|InvalidStateException
     */
    public function process(Form $form, array $submitted, ?Model $record = null, ?string $model = null): array
    {
        return $this->evaluating($record, fn (): array => $this->processWithin($form, $submitted, $record, $model));
    }

    /**
     * @param  array<array-key, mixed>  $submitted
     * @param  class-string<Model>|null  $model
     * @return array<string, mixed>
     */
    private function processWithin(Form $form, array $submitted, ?Model $record, ?string $model): array
    {
        $schema = $this->schema($form);
        $state = $this->normalize($schema, $submitted);
        $access = $this->access($schema, $record);
        $validated = $this->container->make(ValidationEngine::class)->validate($schema, $state, $access, $record, model: $model ?? ($record === null ? null : $record::class));

        return $this->container->make(StateDehydrator::class)->dehydrate($schema, $state, $validated, $access, $record);
    }

    /**
     * Like process(), plus the after-save callbacks of fields that persist
     * with the record (gcego/actions runs them inside its transaction).
     *
     * @param  array<array-key, mixed>  $submitted  Untrusted values.
     * @param  class-string<Model>|null  $model
     *
     * @throws ValidationException|InvalidStateException
     */
    public function submission(Form $form, array $submitted, ?Model $record = null, ?string $model = null): Submission
    {
        return $this->evaluating($record, function () use ($form, $submitted, $record, $model): Submission {
            $schema = $this->schema($form);
            $state = $this->normalize($schema, $submitted);
            $access = $this->access($schema, $record);
            $validated = $this->container->make(ValidationEngine::class)->validate($schema, $state, $access, $record, model: $model ?? ($record === null ? null : $record::class));

            return $this->container->make(StateDehydrator::class)->submission($schema, $state, $validated, $access, $record);
        });
    }

    /**
     * Validate one wizard step (the "Next" button).
     *
     * @param  array<array-key, mixed>  $submitted
     * @param  class-string<Model>|null  $model
     *
     * @throws ValidationException|InvalidStateException
     */
    public function validateStep(Form $form, array $submitted, int $step, ?Model $record = null, ?string $model = null): void
    {
        $this->evaluating($record, function () use ($form, $submitted, $step, $record, $model): void {
            $schema = $this->schema($form);
            $state = $this->normalize($schema, $submitted);
            $this->container->make(ValidationEngine::class)->validate($schema, $state, $this->access($schema, $record), $record, $step, $model ?? ($record === null ? null : $record::class));
        });
    }

    /**
     * Untrusted submission to typed state; every key must be declared by the schema.
     *
     * @param  array<array-key, mixed>  $submitted
     *
     * @throws InvalidStateException
     */
    public function normalize(FormSchema $schema, array $submitted): FormState
    {
        return new FormState($this->normalizeLevel($schema, $submitted, null, ''));
    }

    /**
     * @param  class-string<Model>|null  $model
     */
    public function options(ChoiceField $field, FormState $state, ?string $model = null): OptionCollection
    {
        return $this->container->make(OptionsResolver::class)->options($field, $state, $model);
    }

    public function access(FormSchema $schema, mixed $record = null): FieldAccess
    {
        return FieldAccess::decide($schema, $record, $this->container->make(SecurityManager::class));
    }

    public function conditions(): ConditionEvaluator
    {
        return $this->container->make(ConditionEvaluator::class);
    }

    public function config(): FormsConfig
    {
        return $this->config;
    }

    public function inspect(Form $form): FormReport
    {
        return $this->container->make(FormInspector::class)->inspect($form);
    }

    /**
     * @param  array<array-key, mixed>  $submitted
     * @return array<string, mixed>
     */
    private function normalizeLevel(FormSchema $schema, array $submitted, ?string $repeater, string $prefix): array
    {
        $fields = [];

        foreach ($schema->children($repeater) as $entry) {
            $fields[$entry->name()] = $entry->field;

            if ($entry->field instanceof PasswordInput && $entry->field->isConfirmed()) {
                $fields[$entry->name().'_confirmation'] = $entry->field;
            }
        }

        $state = [];

        foreach ($submitted as $key => $value) {
            $key = (string) $key;
            $field = $fields[$key] ?? null;

            if (! $field instanceof Field) {
                throw InvalidStateException::unknownPath($prefix.$key);
            }

            if ($field instanceof Hidden) {
                continue;
            }

            if ($field instanceof Repeater) {
                $state[$key] = [];

                foreach (is_array($value) ? $value : [] as $itemKey => $item) {
                    if (! StatePath::isItemKey($itemKey) || ! is_array($item)) {
                        throw InvalidStateException::unknownPath($prefix.$key.'.'.$itemKey);
                    }

                    $state[$key][$itemKey] = $this->normalizeLevel($schema, $item, ($repeater === null ? '' : $repeater.'.*.').$key, $prefix.$key.'.'.$itemKey.'.');
                }

                continue;
            }

            $state[$key] = $field->normalizeInput($value, $this->config);
        }

        return $state;
    }
}
