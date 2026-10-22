<?php

declare(strict_types=1);

namespace Gcego\Forms\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Forms\Fields\Checkbox;
use Gcego\Forms\Fields\ChoiceField;
use Gcego\Forms\Fields\DatePicker;
use Gcego\Forms\Fields\Field;
use Gcego\Forms\Fields\Hidden;
use Gcego\Forms\Fields\Repeater;
use Gcego\Forms\FormManager;
use Gcego\Forms\Presets\FormPresets;
use Gcego\Forms\Reactive\FieldInstance;
use Gcego\Forms\Schema\FieldEntry;
use Gcego\Rendering\Blade\BladeRenderer;
use Gcego\Rendering\Context\RenderContext;
use Gcego\Rendering\Contracts\StylingResolver;
use Gcego\Rendering\Output\RenderResult;
use Gcego\Rendering\Renderers\ComponentRenderer;
use Gcego\Security\Fields\FieldAccess as Level;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\HtmlString;

/**
 * Renders every field type through gcego/runtime, each with its own Blade
 * view (overridable in resources/views/vendor/gcego-forms).
 *
 * Fields render only inside a form (a FormRenderFrame). Invisible fields
 * (conditions, Hidden, Access) render nothing; disabled, read-only and
 * non-editable fields render disabled; redacted fields render a mask.
 * Bindings are wire:model (deferred) by default; fields other fields depend
 * on use .live (choices, dates, checkboxes) or .live.debounce (text, with
 * gcego-forms.reactive.debounce), so typing never sends a request per keystroke.
 */
final class FieldRenderer extends BladeRenderer
{
    private string $view = 'gcego-forms::fields.text-input';

    public function __construct(
        Factory $views,
        ComponentRenderer $renderer,
        StylingResolver $styling,
        private readonly FormRenderContext $frames,
        private readonly FormManager $forms,
    ) {
        parent::__construct($views, $renderer, $styling);
    }

    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        $frame = $this->frames->current();
        $entry = $frame === null || ! $component instanceof Field ? null : $this->entry($frame, $component, $context);

        if ($frame === null || $entry === null || $component instanceof Hidden) {
            return RenderResult::empty();
        }

        $instance = new FieldInstance($entry, $entry->concrete($frame->keys), $frame->keys);

        if (! $this->forms->conditions()->visible($instance, $frame->state) || $frame->access->level($entry) === Level::Hidden) {
            return RenderResult::empty();
        }

        $this->view = $entry->field->view();

        return parent::render($component, $context);
    }

    protected function view(): string
    {
        return $this->view;
    }

    protected function data(ComponentInterface $component, RenderContext $context): array
    {
        $frame = $this->frames->current() ?? throw new \LogicException('Fields render inside a form.');
        /** @var Field $component */
        $entry = $this->entry($frame, $component, $context) ?? throw new \LogicException('The field is not part of the form being rendered.');
        $instance = new FieldInstance($entry, $entry->concrete($frame->keys), $frame->keys);
        $conditions = $this->forms->conditions();
        $level = $frame->access->level($entry);
        $id = $frame->idFor($instance->path) ?? $context->domId($instance->path);
        $errors = $frame->errors[$instance->path] ?? [];
        $describedBy = array_values(array_filter([
            $component->get(Field::HelperText) !== null ? $id.'-helper' : null,
            $component->get(Field::Hint) !== null ? $id.'-hint' : null,
            $errors !== [] ? $id.'-error' : null,
        ]));
        $live = $component->isLive() || $frame->isLivePath($entry->path);
        $immediate = $component instanceof ChoiceField || $component instanceof Checkbox || $component instanceof DatePicker;
        $binding = $frame->isLive()
            ? 'wire:model'.($live ? ($immediate ? '.live' : '.live.debounce.'.$this->forms->config()->liveDebounce) : '').'="'.e($frame->binding($instance->path)).'"'
            : 'name="'.e(self::nameAttribute($instance->path)).'"';
        $children = $this->renderer->children($context);

        return [
            'field' => $component,
            'path' => $instance->path,
            'model' => $frame->binding($instance->path),
            'binding' => new HtmlString($binding),
            'live' => $frame->isLive(),
            'id' => $id,
            'label' => (string) __($component->label()),
            'value' => $frame->state->get($instance->path),
            'required' => $conditions->required($instance, $frame->state),
            'disabled' => $conditions->disabled($instance, $frame->state) || ! $level->canWrite(),
            'redacted' => $level === Level::Redacted,
            'errors' => $errors,
            'describedBy' => implode(' ', $describedBy),
            'span' => FormPresets::Span[$component->columnSpan()] ?? '',
            'options' => $component instanceof ChoiceField ? $this->forms->options($component, $frame->state, $frame->model) : null,
            'items' => $component instanceof Repeater ? $conditions->itemKeys($frame->state, $instance->path) : [],
            'renderItem' => fn (string $key): HtmlString => $this->frames->run($frame->withKey($key), static fn (): HtmlString => new HtmlString($children->toHtml())),
            'presets' => FormPresets::class,
        ];
    }

    private function entry(FormRenderFrame $frame, Field $field, RenderContext $context): ?FieldEntry
    {
        $entry = $frame->schema->byComponentPath($context->path());

        return $entry !== null && $entry->field === $field ? $entry : null;
    }

    private static function nameAttribute(string $path): string
    {
        $segments = explode('.', $path);

        return array_shift($segments).implode('', array_map(static fn (string $segment): string => '['.$segment.']', $segments));
    }
}
