<?php

declare(strict_types=1);

namespace Gcego\Widgets\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Widgets\State\WidgetState;

/**
 * One render of a widget tree: its identity, root definition, validated
 * state, the host record, and whether it renders inside the Livewire host.
 *
 * For a registered widget, the identity and root are the widget's own. For a
 * widget embedded in a registered layout or blueprint, they are the owner's:
 * requirements are inherited from the owner down to each widget, and
 * $base is the widget's path in the owner when the widget renders on its own
 * (its WidgetHost), so paths are mapped back into the owner.
 */
final readonly class WidgetRenderFrame
{
    public function __construct(
        public string $identity,
        public ComponentInterface $root,
        public WidgetState $state,
        public string|int|null $record,
        public string $hostId,
        public bool $live = false,
        public ?WidgetDataResult $shared = null,
        public ?string $base = null,
    ) {}

    public function withShared(?WidgetDataResult $shared): self
    {
        return new self($this->identity, $this->root, $this->state, $this->record, $this->hostId, $this->live, $shared, $this->base);
    }

    /**
     * A rendered component path in the root definition's coordinates.
     */
    public function path(string $path): string
    {
        if ($this->base === null) {
            return $path;
        }

        $separator = strpos($path, '.');

        return $this->base.($separator === false ? '' : substr($path, $separator));
    }

    public function id(string $suffix): string
    {
        return $this->hostId.'-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $suffix), '-');
    }
}
