<?php

declare(strict_types=1);

namespace Gcego\Layouts\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One rendered child of a layout. A child with placement classes (a span or
 * visibility) gets a wrapper element; every other child is output as is.
 */
final readonly class LayoutItem implements Htmlable
{
    public function __construct(
        public string $html,
        public ComponentInterface $component,
        public string $classes = '',
        public ?string $key = null,
    ) {}

    public function toHtml(): string
    {
        if ($this->classes === '') {
            return $this->html;
        }

        $key = $this->key === null ? '' : ' wire:key="'.e($this->key).'"';

        return '<div class="'.e($this->classes).'"'.$key.'>'.$this->html.'</div>';
    }
}
