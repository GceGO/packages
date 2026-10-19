<?php

declare(strict_types=1);

namespace Gcego\Tables\Views;

/**
 * A stored table view. $state is untrusted until SavedViewManager restores
 * it through the normalizer with the current user's permissions.
 */
final readonly class SavedView
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $state,
        public int $version,
        public bool $isDefault = false,
    ) {}

    /**
     * @return array{id: string, name: string, default: bool}
     */
    public function summary(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'default' => $this->isDefault];
    }
}
