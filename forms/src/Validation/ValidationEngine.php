<?php

declare(strict_types=1);

namespace Gcego\Forms\Validation;

use Closure;
use Gcego\Forms\Configuration\Rules;
use Gcego\Forms\Configuration\Validate;
use Gcego\Forms\Fields\ChoiceField;
use Gcego\Forms\Fields\FileUpload;
use Gcego\Forms\Fields\Hidden;
use Gcego\Forms\FormsConfig;
use Gcego\Forms\Options\OptionsResolver;
use Gcego\Forms\Reactive\ConditionEvaluator;
use Gcego\Forms\Reactive\FieldInstance;
use Gcego\Forms\Schema\FormSchema;
use Gcego\Forms\State\FieldAccess;
use Gcego\Forms\State\FormState;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Validates submitted state with Laravel's Validator, using only rules from
 * the trusted schema: field properties (Required, MaxLength, Min, ...), Rules
 * nodes, RequiredWhen, allowed options (static, dependent or relationship,
 * re-checked server-side), upload rules, and form-level Validate hooks.
 *
 * Only visible, editable fields are validated: hidden-by-condition, disabled,
 * read-only and unauthorized fields are neither validated nor written.
 */
final readonly class ValidationEngine
{
    public function __construct(
        private Factory $validators,
        private ConditionEvaluator $conditions,
        private OptionsResolver $options,
        private FormsConfig $config,
    ) {}

    /**
     * @param  class-string<Model>|null  $model
     * @return array<string, mixed> The validated values.
     *
     * @throws ValidationException
     */
    public function validate(FormSchema $schema, FormState $state, FieldAccess $access, ?Model $record = null, ?int $step = null, ?string $model = null): array
    {
        $rules = [];
        $attributes = [];

        foreach ($this->writable($schema, $state, $access) as $instance) {
            if ($step !== null && $instance->entry->step !== $step) {
                continue;
            }

            $field = $instance->entry->field;
            $value = $state->get($instance->path);
            $required = $this->conditions->required($instance, $state);

            if ($field->skipsEmpty() && ($value === null || $value === '') && ! $required) {
                continue;
            }

            $fieldRules = [$required ? 'required' : 'nullable', ...$field->typeRules()];

            foreach ($field->configurations()->ofClass(Rules::class) as $node) {
                array_push($fieldRules, ...$node->resolve($record, $state));
            }

            if ($field instanceof ChoiceField) {
                $fieldRules[] = $this->choiceRule($field, $state, $model);
            }

            if ($field instanceof FileUpload) {
                $fieldRules[] = $this->uploadRule($field, $record, $instance->entry->name());
            }

            $rules[$instance->path] = $fieldRules;
            $attributes[$instance->path] = (string) __($field->label());
        }

        $validator = $this->validators->make($state->all(), $rules, [], $attributes);

        foreach ($schema->form->configurations()->ofClass(Validate::class) as $node) {
            $validator->after(static fn (Validator $validator) => ($node->after)($validator, $state, $record));
        }

        /** @var array<string, mixed> */
        return $validator->validate();
    }

    /**
     * Visible fields whose submitted values may be written.
     *
     * @return list<FieldInstance>
     */
    public function writable(FormSchema $schema, FormState $state, FieldAccess $access): array
    {
        return array_values(array_filter(
            $this->conditions->instances($schema, $state),
            fn (FieldInstance $instance): bool => ! $instance->entry->field instanceof Hidden
                && $instance->entry->field->acceptsSubmission()
                && $access->editable($instance->entry)
                && ! $this->conditions->disabled($instance, $state),
        ));
    }

    /**
     * @param  class-string<Model>|null  $model
     */
    private function choiceRule(ChoiceField $field, FormState $state, ?string $model): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($field, $state, $model): void {
            $values = is_array($value) ? array_values($value) : [$value];
            $scalars = array_values(array_filter($values, static fn (mixed $item): bool => is_int($item) || is_string($item)));

            if (count($scalars) !== count($values) || ! $this->options->allows($field, $scalars, $state, $model)) {
                $fail((string) __('validation.in', ['attribute' => __($field->label())]));
            }
        };
    }

    private function uploadRule(FileUpload $field, ?Model $record, string $name): Closure
    {
        $current = $record === null ? null : $field->currentValue($record);

        return function (string $attribute, mixed $value, Closure $fail) use ($field, $current): void {
            $files = $field->isMultiple() ? (is_array($value) ? $value : [$value]) : [$value];
            $existing = is_array($current) ? $current : ($current === null ? [] : [$current]);
            $max = $field->get(FileUpload::MaxFiles);

            if ($field->isMultiple() && is_int($max) && count($files) > $max) {
                $fail((string) __('validation.max.array', ['attribute' => __($field->label()), 'max' => $max]));

                return;
            }

            foreach ($files as $file) {
                if (is_string($file)) {
                    // Only the record's current file may be kept; a path can never point elsewhere.
                    if (! in_array($file, $existing, true)) {
                        $fail((string) __('validation.uploaded', ['attribute' => __($field->label())]));
                    }

                    continue;
                }

                if (! $file instanceof UploadedFile) {
                    $fail((string) __('validation.file', ['attribute' => __($field->label())]));

                    continue;
                }

                $check = $this->validators->make(['file' => $file], ['file' => $field->fileRules($this->config)], [], ['file' => (string) __($field->label())]);

                foreach ($check->errors()->all() as $message) {
                    $fail($message);
                }
            }
        };
    }
}
