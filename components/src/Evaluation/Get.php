<?php

declare(strict_types=1);

namespace Gcego\Components\Evaluation;

/**
 * Reads state relative to the evaluated component's container.
 *
 *     fn (Get $get): bool => $get('type') === 'business'
 *     fn (Get $get): ?string => $get('../country')
 */
final readonly class Get
{
    public function __construct(private State $state, private string $containerPath = '') {}

    public function __invoke(string $path = '', bool $isAbsolute = false): mixed
    {
        return $this->state->get(StatePath::resolve($this->containerPath, $path, $isAbsolute));
    }

    public function string(string $path, bool $isAbsolute = false): ?string
    {
        $value = $this($path, $isAbsolute);

        return is_scalar($value) ? (string) $value : null;
    }

    public function boolean(string $path, bool $isAbsolute = false): bool
    {
        return filter_var($this($path, $isAbsolute), FILTER_VALIDATE_BOOLEAN);
    }

    public function filled(string $path, bool $isAbsolute = false): bool
    {
        return filled($this($path, $isAbsolute));
    }

    public function blank(string $path, bool $isAbsolute = false): bool
    {
        return blank($this($path, $isAbsolute));
    }
}
