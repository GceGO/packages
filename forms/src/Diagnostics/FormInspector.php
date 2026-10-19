<?php

declare(strict_types=1);

namespace Gcego\Forms\Diagnostics;

use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Forms\Configuration\Access;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Configuration\Rules;
use Gcego\Forms\Fields\ChoiceField;
use Gcego\Forms\Fields\FileUpload;
use Gcego\Forms\Form;
use Gcego\Forms\FormsConfig;
use Gcego\Forms\Options\OptionsResolver;
use Gcego\Forms\Schema\SchemaCompiler;
use Gcego\Runtime\Rendering\RendererRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Describes a form without executing or querying anything: fields (path,
 * type, rules, conditions, options, access), and problems: schema errors
 * (duplicates, unknown dependencies, cycles), choice fields without options,
 * invalid relationships for the model, disallowed upload disks, missing
 * renderers or styling slots. Never reports values.
 */
final readonly class FormInspector
{
    public function __construct(private Container $container, private FormsConfig $config) {}

    /**
     * @param  class-string<Model>|null  $model  The form's model, to check relationships.
     */
    public function inspect(Form $form, ?string $model = null): FormReport
    {
        try {
            $schema = (new SchemaCompiler)->compile($form);
        } catch (Throwable $exception) {
            return new FormReport($form->formName(), [], [new FormDiagnostic('schema', FormDiagnostic::Error, $exception->getMessage())]);
        }

        $renderers = $this->container->bound(RendererRegistry::class) ? $this->container->make(RendererRegistry::class) : null;
        $slots = $this->container->bound(SlotRegistry::class) ? $this->container->make(SlotRegistry::class) : null;
        $fields = [];
        $diagnostics = [];

        foreach ($schema->entries as $entry) {
            $field = $entry->field;
            $details = ['field' => $entry->path];
            $fields[] = [
                'path' => $entry->path,
                'type' => $field::class,
                'required' => $field->isRequired(),
                'rules' => array_merge(...array_map(static fn (Rules $rules): array => $rules->normalize()['rules'], $field->configurations()->ofClass(Rules::class)) ?: [[]]),
                'conditions' => array_map(static fn (array $visibility): string => $visibility['condition']->condition->describe(), $entry->visibility),
                'options' => $field instanceof ChoiceField ? ($field->relationship() !== null ? 'relationship:'.$field->relationship()->name : ($field->options()?->normalize()['options'] ?? null)) : null,
                'access' => $field->configurations()->has(Access::class),
                'live' => $schema->isLive($entry->path),
                'step' => $entry->step,
            ];

            if ($field instanceof ChoiceField && $field->options() === null && $field->relationship() === null) {
                $diagnostics[] = new FormDiagnostic('options', FormDiagnostic::Error, sprintf('%s has no Options or Relationship: nothing can be selected.', $entry->path), $details);
            }

            if ($field instanceof ChoiceField && $field->relationship() !== null) {
                if ($model === null) {
                    $diagnostics[] = new FormDiagnostic('relationship', FormDiagnostic::Warning, sprintf('%s uses a relationship; inspect with the model to check it.', $entry->path), $details);
                } else {
                    try {
                        OptionsResolver::related($model, $field->relationship());
                    } catch (Throwable $exception) {
                        $diagnostics[] = new FormDiagnostic('relationship', FormDiagnostic::Error, $exception->getMessage(), $details);
                    }
                }
            }

            if ($field instanceof FileUpload && ! in_array($field->disk($this->config), $this->config->uploadDisks, true)) {
                $diagnostics[] = new FormDiagnostic('uploads', FormDiagnostic::Error, sprintf('%s stores to a disk that is not in gcego-forms.uploads.disks.', $entry->path), $details);
            }

            if ($renderers !== null && ! $renderers->has($field)) {
                $diagnostics[] = new FormDiagnostic('renderer', FormDiagnostic::Error, sprintf('%s (%s) has no renderer.', $entry->path, $field::class), $details);
            }

            if ($slots !== null && $slots->for($field) === null) {
                $diagnostics[] = new FormDiagnostic('styling', FormDiagnostic::Warning, sprintf('%s (%s) has no styling slots.', $entry->path, $field::class), $details);
            }

            if (array_filter($field->configurations()->all(), static fn (mixed $node): bool => $node instanceof Options && $node->source instanceof \Closure && $node->dependsOn === []) !== []) {
                $diagnostics[] = new FormDiagnostic('reactive', FormDiagnostic::Warning, sprintf('%s computes options with a closure but declares no dependsOn: they only refresh on full renders.', $entry->path), $details);
            }
        }

        if ($diagnostics === []) {
            $diagnostics[] = new FormDiagnostic('form', FormDiagnostic::Ok, sprintf('Form "%s" has %d field(s) and no problems.', $form->formName(), count($schema->entries)));
        }

        return new FormReport($form->formName(), $fields, $diagnostics);
    }
}
