<?php

declare(strict_types=1);

namespace Gcego\Forms\State;

use Gcego\Forms\Contracts\PersistsWithRecord;
use Gcego\Forms\Exceptions\InvalidFieldException;
use Gcego\Forms\Fields\FileUpload;
use Gcego\Forms\Fields\Hidden;
use Gcego\Forms\Fields\Repeater;
use Gcego\Forms\FormsConfig;
use Gcego\Forms\Reactive\ConditionEvaluator;
use Gcego\Forms\Reactive\FieldInstance;
use Gcego\Forms\Schema\FormSchema;
use Gcego\Forms\Validation\ValidationEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Validated state to the data that may be persisted.
 *
 * A value is written only when its field is visible, editable for this user
 * (Access), not disabled or read-only, Dehydrated, and present in the
 * submission (an absent key never overwrites a stored value). Hidden fields
 * write their server value; empty passwords write nothing; repeaters become
 * lists in item order; uploads are stored and become paths.
 */
final class StateDehydrator
{
    /** @var list<\Closure(Model): void> Collected during one submission() call. */
    private array $afterSave = [];

    /** @var array<string, list<string>> */
    private array $stageErrors = [];

    public function __construct(
        private readonly ConditionEvaluator $conditions,
        private readonly ValidationEngine $validation,
        private readonly FormsConfig $config,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function dehydrate(FormSchema $schema, FormState $state, array $validated, FieldAccess $access, ?Model $record = null): array
    {
        return $this->submission($schema, $state, $validated, $access, $record)->data;
    }

    /**
     * Attribute data plus after-save callbacks of PersistsWithRecord fields.
     *
     * @param  array<string, mixed>  $validated
     */
    public function submission(FormSchema $schema, FormState $state, array $validated, FieldAccess $access, ?Model $record = null): Submission
    {
        $this->afterSave = [];
        $this->stageErrors = [];
        $data = $this->dehydrateLevel($schema, $state, $validated, $access, $record);
        $afterSave = $this->afterSave;
        $errors = $this->pullStageErrors();
        $this->afterSave = [];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new Submission($data, $afterSave);
    }

    /**
     * @return array<string, list<string>>
     */
    private function pullStageErrors(): array
    {
        $errors = $this->stageErrors;
        $this->stageErrors = [];

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function dehydrateLevel(FormSchema $schema, FormState $state, array $validated, FieldAccess $access, ?Model $record = null): array
    {
        $writable = [];

        foreach ($this->validation->writable($schema, $state, $access) as $instance) {
            $writable[$instance->path] = $instance;
        }

        return $this->level($schema, $state, new FormState($validated), $writable, $access, $record, null, []);
    }

    /**
     * @param  array<string, FieldInstance>  $writable
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function level(FormSchema $schema, FormState $state, FormState $validated, array $writable, FieldAccess $access, ?Model $record, ?string $repeater, array $keys): array
    {
        $data = [];

        foreach ($schema->children($repeater) as $entry) {
            $field = $entry->field;
            $path = $entry->concrete($keys);

            if (! $field->isDehydrated()) {
                continue;
            }

            if ($field instanceof Hidden) {
                if ($repeater === null && $this->conditions->visible(new FieldInstance($entry, $path, $keys), $state)) {
                    $data[$entry->name()] = $record?->exists === true ? $record->getAttribute($entry->name()) : $field->emptyState();
                }

                continue;
            }

            $instance = $writable[$path] ?? null;

            if ($instance === null || ! $state->has($path)) {
                continue;
            }

            if ($field instanceof Repeater) {
                $items = [];

                foreach ($this->conditions->itemKeys($state, $path) as $key) {
                    $items[] = $this->level($schema, $state, $validated, $writable, $access, $record, $entry->path, [...$keys, $key]);
                }

                $data[$entry->name()] = $items;

                continue;
            }

            $value = $validated->get($path);

            if ($field->skipsEmpty() && ($value === null || $value === '')) {
                continue;
            }

            if ($field instanceof PersistsWithRecord) {
                if ($repeater !== null) {
                    throw new InvalidFieldException(sprintf('Field "%s" persists with the record and cannot be inside a repeater.', $field->fieldName()));
                }

                try {
                    $staged = $field->stage($value, $record, $this->config);
                } catch (ValidationException $exception) {
                    // Every field's problems are reported together.
                    foreach ($exception->errors() as $key => $messages) {
                        $this->stageErrors[(string) $key] = [...($this->stageErrors[(string) $key] ?? []), ...array_values(array_filter((array) $messages, is_string(...)))];
                    }

                    continue;
                }

                $this->afterSave[] = static function (Model $saved) use ($field, $staged): void {
                    $field->persist($saved, $staged);
                };

                continue;
            }

            $data[$entry->name()] = $field instanceof FileUpload ? $this->store($field, $value) : $field->dehydrate($value, $this->config);
        }

        return $data;
    }

    private function store(FileUpload $field, mixed $value): mixed
    {
        $disk = $field->disk($this->config);

        if (! in_array($disk, $this->config->uploadDisks, true)) {
            throw new InvalidFieldException(sprintf('FileUpload "%s" may not store to disk "%s" (gcego-forms.uploads.disks).', $field->fieldName(), $disk));
        }

        $store = function (mixed $file) use ($field, $disk): mixed {
            if (! $file instanceof UploadedFile) {
                return $file;
            }

            // Random file name, private visibility, a trusted directory.
            $path = $file->storeAs($field->directory($this->config), $file->hashName(), ['disk' => $disk, 'visibility' => 'private']);

            return $path === false ? null : $path;
        };

        return is_array($value) ? array_values(array_filter(array_map($store, $value))) : $store($value);
    }
}
