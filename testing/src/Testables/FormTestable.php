<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Forms\Exceptions\InvalidStateException;
use Gcego\Forms\Fields\Field;
use Gcego\Forms\Form;
use Gcego\Forms\FormManager;
use Gcego\Forms\Reactive\FieldInstance;
use Gcego\Forms\Schema\FieldEntry;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Support\Reports;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A gcego/forms Form processed by FormManager exactly as a submission is:
 * normalized, checked against the declared fields and the acting user's
 * field access, validated and dehydrated. Nothing is persisted.
 *
 * Paths use the form's dot notation; repeater items use their item keys
 * ("objectives.iaaaaaaaaaa.title").
 */
final class FormTestable extends Testable
{
    /** @var array<string, mixed> */
    private array $submitted = [];

    private ?Model $record = null;

    /** @var array<string, mixed>|null */
    private ?array $processed = null;

    private ?Throwable $failure = null;

    private bool $ran = false;

    /**
     * @param  class-string<Model>|null  $model  The model validation rules refer to (defaults to the record's class).
     */
    public function __construct(Verifier $verifier, private readonly Container $container, private readonly TestEnvironment $environment, private readonly Form $form, private readonly ?string $model = null)
    {
        parent::__construct($verifier);
    }

    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $tenant, $guard);
        $this->ran = false;

        return $this;
    }

    /**
     * The record being edited: hydration reads it, and validation rules
     * (unique, closures) receive it.
     */
    public function record(?Model $record): self
    {
        $this->record = $record;
        $this->ran = false;

        return $this;
    }

    /**
     * Sets the submitted state. Nothing is checked until an assertion runs.
     *
     * @param  array<string, mixed>  $state
     */
    public function fill(array $state): self
    {
        $this->submitted = $state;
        $this->ran = false;

        return $this;
    }

    /**
     * The dehydrated data FormManager::process() returned.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $this->process();

        return $this->processed ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function hydrated(): array
    {
        return $this->manager()->hydrate($this->form, $this->record);
    }

    public function assertValid(): self
    {
        $this->process();

        return $this->check($this->failure === null, 'assertValid()', 'Expected the submission to be valid.', ['errors' => []], ['errors' => $this->errors(), 'exception' => $this->failure instanceof ValidationException ? null : $this->describe($this->failure)]);
    }

    /**
     * Validation fails; with paths, each has an error. With messages keyed
     * by path, the error contains the text.
     *
     * @param  list<string>|array<string, string>  $fields
     */
    public function assertInvalid(array $fields = []): self
    {
        $this->process();
        $errors = $this->errors();
        $problems = [];

        foreach ($fields as $key => $value) {
            $path = is_int($key) ? $value : $key;

            if (! isset($errors[$path])) {
                $problems[$path] = '(no error)';
            } elseif (! is_int($key) && ! str_contains(implode(' ', $errors[$path]), $value)) {
                $problems[$path] = $errors[$path];
            }
        }

        return $this->check($this->failure instanceof ValidationException && $problems === [], 'assertInvalid('.$this->export($fields).')', 'Expected the submission to fail validation.', ['errors' => $fields], ['errors' => $errors, 'differences' => $problems]);
    }

    public function assertState(string $path, mixed $value): self
    {
        $data = $this->data();

        return $this->check($this->failure === null && Arr::has($data, $path) && Arr::get($data, $path) === $value, sprintf('assertState(%s, %s)', $this->export($path), $this->export($value)), sprintf('Expected the processed value of [%s].', $path), [$path => $value], [$path => Arr::has($data, $path) ? Arr::get($data, $path) : '(absent)', 'errors' => $this->errors()]);
    }

    /**
     * The submitted value was dropped (read-only, disabled, hidden by a
     * condition, or not writable by the acting user) and not returned.
     */
    public function assertDropped(string $path): self
    {
        $data = $this->data();

        return $this->check($this->failure === null && ! Arr::has($data, $path), sprintf('assertDropped(%s)', $this->export($path)), sprintf('Expected the submitted [%s] to be ignored.', $path), [$path => '(absent)'], [$path => Arr::get($data, $path), 'exception' => $this->describe($this->failure)]);
    }

    /**
     * The submission is refused because it contains a path that is not a
     * field of the form (a forged or undeclared key).
     */
    public function assertRejectsUnknownField(?string $path = null): self
    {
        $this->process();

        return $this->check($this->failure instanceof InvalidStateException && ($path === null || str_contains($this->failure->getMessage(), '"'.$path.'"')), sprintf('assertRejectsUnknownField(%s)', $this->export($path)), 'Expected the submission to be refused for an unknown field.', ['exception' => InvalidStateException::class, 'path' => $path], ['exception' => $this->describe($this->failure)]);
    }

    public function assertHydrated(string $path, mixed $value): self
    {
        $data = $this->hydrated();

        return $this->check(Arr::has($data, $path) && Arr::get($data, $path) === $value, sprintf('assertHydrated(%s, %s)', $this->export($path), $this->export($value)), sprintf('Expected the form to load [%s] with the value.', $path), [$path => $value], [$path => Arr::has($data, $path) ? Arr::get($data, $path) : '(absent)']);
    }

    /**
     * The field is not loaded into the form state (unreadable by the
     * acting user, a password or a server-only field).
     */
    public function assertNotHydrated(string $path): self
    {
        $data = $this->hydrated();

        return $this->check(! Arr::has($data, $path) || Arr::get($data, $path) === null, sprintf('assertNotHydrated(%s)', $this->export($path)), sprintf('Expected [%s] not to be loaded.', $path), [$path => '(absent)'], [$path => Arr::get($data, $path)]);
    }

    public function assertHasField(string $path): self
    {
        return $this->check($this->entry($path) !== null, sprintf('assertHasField(%s)', $this->export($path)), sprintf('Expected the form to declare [%s].', $path), ['field' => $path], ['fields' => $this->manager()->schema($this->form)->paths()]);
    }

    public function assertMissingField(string $path): self
    {
        return $this->check($this->entry($path) === null, sprintf('assertMissingField(%s)', $this->export($path)), sprintf('Expected the form not to declare [%s].', $path), ['field' => 'absent'], ['field' => 'declared']);
    }

    public function assertFieldLabel(string $path, string $label): self
    {
        $actual = $this->field($path)?->label();

        return $this->check($actual === $label, sprintf('assertFieldLabel(%s, %s)', $this->export($path), $this->export($label)), sprintf('Expected the label of [%s].', $path), ['label' => $label], ['label' => $actual]);
    }

    public function assertFieldRequired(string $path, bool $required = true): self
    {
        $actual = $this->field($path)?->isRequired();

        return $this->check($actual === $required, sprintf('assertFieldRequired(%s, %s)', $this->export($path), $this->export($required)), sprintf('Expected [%s] to be %s.', $path, $required ? 'required' : 'optional'), ['required' => $required], ['required' => $actual]);
    }

    public function assertFieldDisabled(string $path, bool $disabled = true): self
    {
        $actual = $this->field($path)?->isDisabled();

        return $this->check($actual === $disabled, sprintf('assertFieldDisabled(%s, %s)', $this->export($path), $this->export($disabled)), sprintf('Expected [%s] to be %s.', $path, $disabled ? 'disabled' : 'enabled'), ['disabled' => $disabled], ['disabled' => $actual]);
    }

    public function assertFieldDefault(string $path, mixed $value): self
    {
        $field = $this->field($path);
        $actual = $field?->get(Field::Default);

        return $this->check($field !== null && $actual === $value, sprintf('assertFieldDefault(%s, %s)', $this->export($path), $this->export($value)), sprintf('Expected the default of [%s].', $path), ['default' => $value], ['default' => $actual]);
    }

    /**
     * Visible for the filled state (VisibleWhen conditions and the Hidden
     * property are evaluated as on the server).
     */
    public function assertFieldVisible(string $path, bool $visible = true): self
    {
        $actual = in_array($path, $this->visiblePaths(), true);

        return $this->check($actual === $visible, sprintf('assertFieldVisible(%s, %s)', $this->export($path), $this->export($visible)), sprintf('Expected [%s] to be %s for the filled state.', $path, $visible ? 'visible' : 'hidden'), ['visible' => $visible], ['visible' => $actual, 'visible fields' => $this->visiblePaths()]);
    }

    public function assertHealthy(): self
    {
        $problems = Reports::problems($this->manager()->inspect($this->form));

        return $this->check($problems === [], 'assertHealthy()', 'Expected the form inspector to report no errors.', ['errors' => []], ['errors' => $problems]);
    }

    protected function subject(): string
    {
        return 'form';
    }

    protected function context(): array
    {
        return ['Form' => $this->form->formName(), 'Submitted' => $this->submitted, 'Record' => $this->record?->getKey() ?? '(none)'];
    }

    private function process(): void
    {
        if ($this->ran) {
            return;
        }

        $this->ran = true;
        $this->processed = null;
        $this->failure = null;

        try {
            $this->processed = $this->manager()->process($this->form, $this->submitted, $this->record, $this->model ?? ($this->record === null ? null : $this->record::class));
        } catch (ValidationException|InvalidStateException $exception) {
            $this->failure = $exception;
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function errors(): array
    {
        if (! $this->failure instanceof ValidationException) {
            return [];
        }

        /** @var array<string, list<string>> $errors */
        $errors = $this->failure->errors();

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function visiblePaths(): array
    {
        $manager = $this->manager();
        $schema = $manager->schema($this->form);

        return array_map(static fn (FieldInstance $instance): string => $instance->path, $manager->conditions()->instances($schema, $manager->normalize($schema, $this->submitted)));
    }

    private function entry(string $path): ?FieldEntry
    {
        return $this->manager()->schema($this->form)->entry($path);
    }

    private function field(string $path): ?Field
    {
        $field = $this->entry($path)?->field;

        return $field instanceof Field ? $field : null;
    }

    private function manager(): FormManager
    {
        return $this->container->make(FormManager::class);
    }
}
