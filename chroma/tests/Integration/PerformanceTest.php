<?php

declare(strict_types=1);

use Gcego\Chroma\Slot;
use Gcego\Chroma\Slots\SlotResolver;
use Gcego\Chroma\Tailwind;
use Gcego\Chroma\Tests\Fixtures\ExampleCard;

it('resolves slots for hundreds of configured components quickly', function (): void {
    $resolver = app(SlotResolver::class);
    $cards = array_map(fn (int $index): ExampleCard => new ExampleCard('Card '.$index, new Tailwind(new Slot(Tailwind::Body, ['grid', 'md:grid-cols-'.(($index % 4) + 1)]))), range(1, 500));
    $start = hrtime(true);

    foreach ($cards as $card) {
        foreach ([Tailwind::Wrapper, Tailwind::Header, Tailwind::Title, Tailwind::Body] as $slot) {
            $resolver->resolve($card, $slot);
        }
    }

    // Regression guard, not a benchmark (see benchmarks/run.php).
    expect((hrtime(true) - $start) / 1e6)->toBeLessThan(2000.0);
});
