<?php

declare(strict_types=1);

namespace Gcego\Luminaries\View\Components;

use BackedEnum;
use Gcego\Luminaries\IconName;
use Gcego\Luminaries\Luminaries;
use Gcego\Luminaries\Rendering\IconOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;

/**
 * <x-gcego-icon name="actions.edit" size="lg" tone="primary" label="Edit" />
 *
 * Without a label (or with "decorative") the icon is hidden from assistive
 * technology. Extra attributes are checked by AttributeGuard.
 */
final class Icon extends Component
{
    public function __construct(
        private readonly Luminaries $luminaries,
        public string|BackedEnum $name,
        public ?string $size = null,
        public ?string $tone = null,
        public ?string $label = null,
        public bool $decorative = false,
        public float|int|string|null $strokeWidth = null,
    ) {
        $this->name = IconName::of($name);
    }

    public function svg(ComponentAttributeBag $attributes): HtmlString
    {
        $extra = $attributes->except(['class'])->getAttributes();
        $class = $attributes->get('class');

        return new HtmlString($this->luminaries->html($this->name, new IconOptions(
            size: $this->size,
            tone: $this->tone,
            class: is_string($class) ? $class : '',
            label: $this->label,
            decorative: $this->decorative,
            strokeWidth: is_numeric($this->strokeWidth) ? (float) $this->strokeWidth : null,
            attributes: $extra,
        )));
    }

    public function render(): View
    {
        return view('luminaries::components.icon');
    }
}
