<?php

declare(strict_types=1);

namespace Gcego\Luminaries\View\Components;

use BackedEnum;
use Gcego\Luminaries\Exceptions\InvalidConfigurationException;
use Gcego\Luminaries\IconName;
use Gcego\Luminaries\Luminaries;
use Gcego\Luminaries\Presets\IconButtonPresets;
use Gcego\Luminaries\Rendering\IconOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

/**
 * <x-gcego-icon-button icon="actions.edit" label="Edit student" wire:click="edit" />
 *
 * An icon-only button (or link, with href) whose accessible name is the
 * required label: the icon itself is decorative and the label is rendered
 * as screen-reader text and as the tooltip.
 */
final class IconButton extends Component
{
    public function __construct(
        private readonly Luminaries $luminaries,
        public string|BackedEnum $icon,
        public string $label = '',
        public ?string $size = null,
        public ?string $tone = null,
        public ?string $href = null,
        public string $type = 'button',
    ) {
        $this->icon = IconName::of($icon);

        if (trim($label) === '') {
            throw InvalidConfigurationException::invalid('icon-button', sprintf('<x-gcego-icon-button icon="%s"> needs a label: icon-only buttons must have an accessible name.', $this->icon));
        }

        if (! in_array($type, ['button', 'submit', 'reset'], true)) {
            throw InvalidConfigurationException::invalid('icon-button', 'type must be button, submit or reset.');
        }
    }

    public function svg(): HtmlString
    {
        return new HtmlString($this->luminaries->html($this->icon, new IconOptions(size: $this->size, tone: $this->tone, decorative: true)));
    }

    public function classes(): string
    {
        return implode(' ', IconButtonPresets::Button);
    }

    public function render(): View
    {
        return view('luminaries::components.icon-button');
    }
}
