<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Tests\Fixtures\Livewire;

use Livewire\Component;

final class IconToggle extends Component
{
    public bool $visible = false;

    public function toggle(): void
    {
        $this->visible = ! $this->visible;
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <button type="button" wire:click="toggle">
                    <x-gcego-icon :name="$visible ? 'authentication.hide-password' : 'authentication.show-password'" wire:key="eye" />
                    <span>Toggle</span>
                </button>
            </div>
            BLADE;
    }
}
