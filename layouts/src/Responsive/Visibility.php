<?php

declare(strict_types=1);

namespace Gcego\Layouts\Responsive;

use Gcego\Components\Contracts\PlacementConfiguration;
use Gcego\Layouts\Exceptions\InvalidLayoutException;
use Gcego\Layouts\Presets\LayoutPresets;

/**
 * Hide a layout child at some widths (any child of a layout accepts it):
 *
 *     new Visibility(hiddenBelow: 'md')               // from md up only
 *     new Visibility(hiddenFrom: 'lg')                // below lg only
 *
 * Presentational only: hidden content is still rendered (and its data
 * loaded) and present for assistive technology users at other widths. It is
 * never a security boundary; use requirements to withhold content.
 */
final readonly class Visibility implements PlacementConfiguration
{
    public ?Breakpoint $hiddenBelow;

    public ?Breakpoint $hiddenFrom;

    public function __construct(?string $hiddenBelow = null, ?string $hiddenFrom = null)
    {
        $this->hiddenBelow = $hiddenBelow === null ? null : Breakpoint::parse($hiddenBelow, 'Visibility');
        $this->hiddenFrom = $hiddenFrom === null ? null : Breakpoint::parse($hiddenFrom, 'Visibility');

        if ($this->hiddenBelow === Breakpoint::Default || $this->hiddenFrom === Breakpoint::Default) {
            throw InvalidLayoutException::invalid('Visibility', 'use sm, md, lg, xl or 2xl ("default" would hide the child everywhere).');
        }

        if ($this->hiddenBelow === null && $this->hiddenFrom === null) {
            throw InvalidLayoutException::invalid('Visibility', 'set hiddenBelow, hiddenFrom or both.');
        }

        if ($this->hiddenBelow !== null && $this->hiddenFrom !== null && $this->hiddenFrom->order() <= $this->hiddenBelow->order()) {
            throw InvalidLayoutException::invalid('Visibility', 'hiddenFrom must be a larger breakpoint than hiddenBelow, or the child is never visible.');
        }
    }

    public function classes(): string
    {
        return trim(($this->hiddenBelow === null ? '' : LayoutPresets::HiddenBelow[$this->hiddenBelow->value]).' '.($this->hiddenFrom === null ? '' : LayoutPresets::HiddenFrom[$this->hiddenFrom->value]));
    }

    /**
     * @return array{hidden_below: string|null, hidden_from: string|null}
     */
    public function normalize(): array
    {
        return ['hidden_below' => $this->hiddenBelow?->value, 'hidden_from' => $this->hiddenFrom?->value];
    }
}
