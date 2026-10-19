<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tests\Fixtures\Livewire;

use Illuminate\Support\Facades\Blade;
use Livewire\Component;

/**
 * A page component that renders the navigation, like a panel shell would.
 */
final class Shell extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): string
    {
        return Blade::render('<div><span>Clicked {{ $count }}</span><x-gcego-navigation::navigation name="admin" /></div>', ['count' => $this->count]);
    }
}
