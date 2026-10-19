<?php

declare(strict_types=1);

namespace Gcego\Forms\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Forms\Form;
use Gcego\Forms\FormManager;
use Gcego\Forms\State\FormState;
use Gcego\Runtime\Blade\BladeRenderer;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Rendering\ComponentRenderer;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;
use Illuminate\Contracts\View\Factory;
use Livewire\Livewire;

/**
 * Renders a Form:
 *
 * - inside a FormHost (a frame is active): its fields and layouts;
 * - as a part of a registered blueprint: mounts the Livewire FormHost for it
 *   (Edit when the render metadata has a "record", otherwise Create);
 * - elsewhere: static, read-only HTML of its defaults (no submission).
 */
final class FormRenderer extends BladeRenderer
{
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
        if (! $component instanceof Form) {
            return RenderResult::empty();
        }

        if ($this->frames->current() !== null) {
            return parent::render($component, $context);
        }

        if ($context->identity !== null && ! $context->node->isRoot()) {
            $record = $context->metadata['record'] ?? null;

            return RenderResult::trustedHtml((string) Livewire::mount('gcego-form', [
                'definition' => $context->identity,
                'record' => is_int($record) || is_string($record) ? $record : null,
            ], 'gcego-form-'.md5($context->identity.'|'.(is_scalar($record) ? (string) $record : ''))));
        }

        $schema = $this->forms->schema($component);
        $frame = new FormRenderFrame($schema, new FormState($this->forms->hydrate($component)), $this->forms->access($schema));

        return RenderResult::trustedHtml($this->frames->run($frame, fn (): string => parent::render($component, $context)->toHtml()));
    }

    protected function view(): string
    {
        return 'gcego-forms::forms.form';
    }
}
