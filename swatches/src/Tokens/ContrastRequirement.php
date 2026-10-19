<?php

declare(strict_types=1);

namespace Gcego\Swatches\Tokens;

/**
 * WCAG 2.2 AA contrast minimums (success criteria 1.4.3 and 1.4.11).
 */
enum ContrastRequirement: string
{
    /** Body text and labels: 4.5:1. */
    case Text = 'text';

    /** Large text (≥ 24px, or ≥ 18.66px bold): 3:1. */
    case LargeText = 'large-text';

    /** Component boundaries, focus indicators, icons and chart marks: 3:1. */
    case NonText = 'non-text';

    public function minimum(): float
    {
        return match ($this) {
            self::Text => 4.5,
            self::LargeText, self::NonText => 3.0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Text => 'text (4.5:1)',
            self::LargeText => 'large text (3:1)',
            self::NonText => 'non-text UI (3:1)',
        };
    }
}
