<?php

declare(strict_types=1);

namespace Gcego\Components\Evaluation;

/**
 * Writes state relative to the evaluated component's container.
 *
 *     fn (Set $set, ?string $state) => $set('slug', Str::slug($state))
 */
final readonly class Set
{
    public function __construct(private State $state, private string $containerPath = '') {}

    public function __invoke(string $path, mixed $value, bool $isAbsolute = false): void
    {
        $this->state->set(StatePath::resolve($this->containerPath, $path, $isAbsolute), $value);
    }
}
