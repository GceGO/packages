<?php

declare(strict_types=1);

namespace Gcego\Components\Live;

/**
 * What one state update changed: the state paths written (by the update and
 * by afterStateUpdated hooks) and the components whose resolution differs,
 * including components that appeared or disappeared. Renderers redraw only
 * these.
 */
final readonly class Changes
{
    /**
     * @param  list<string>  $statePaths
     * @param  list<string>  $components  Component tree paths, in tree order.
     */
    public function __construct(
        public array $statePaths,
        public array $components,
    ) {}

    public function isEmpty(): bool
    {
        return $this->components === [];
    }

    public function touches(string $componentPath): bool
    {
        return in_array($componentPath, $this->components, true);
    }

    /**
     * @return array{statePaths: list<string>, components: list<string>}
     */
    public function toArray(): array
    {
        return ['statePaths' => $this->statePaths, 'components' => $this->components];
    }
}
