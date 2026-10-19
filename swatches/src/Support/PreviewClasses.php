<?php

declare(strict_types=1);

namespace Gcego\Swatches\Support;

/**
 * Class lists the color explorer uses to preview components.
 *
 * The defaults use semantic utilities only. gcego/chroma replaces them with its real
 * presets (control.button.primary, control.input, data.table …) when it is installed,
 * so the preview shows the shared component styling rather than a copy of it.
 */
final class PreviewClasses
{
    /**
     * @var array<string, string>
     */
    public const array Defaults = [
        'card' => 'rounded-lg border border-border bg-card text-card-foreground p-6',
        'button.primary' => 'inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
        'button.secondary' => 'inline-flex items-center rounded-md bg-secondary px-4 py-2 text-sm font-medium text-secondary-foreground hover:bg-secondary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
        'button.danger' => 'inline-flex items-center rounded-md bg-danger px-4 py-2 text-sm font-medium text-danger-foreground hover:bg-danger-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
        'input' => 'block w-full rounded-md border border-input bg-surface px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-ring aria-invalid:border-invalid',
        'table' => 'w-full border-collapse text-left text-sm',
        'table.head' => 'bg-surface-muted',
        'table.header-cell' => 'border-b border-border px-4 py-2 font-medium text-muted-foreground',
        'table.row' => 'border-b border-border hover:bg-accent',
        'table.cell' => 'px-4 py-2 text-foreground',
        'nav.item' => 'flex items-center rounded-md px-3 py-2 text-sm text-sidebar-foreground hover:bg-accent aria-[current=page]:bg-sidebar-active aria-[current=page]:text-sidebar-active-foreground',
        'tone.success' => 'border-success/30 bg-success-soft text-success',
        'tone.warning' => 'border-warning/30 bg-warning-soft text-warning',
        'tone.danger' => 'border-danger/30 bg-danger-soft text-danger',
        'tone.info' => 'border-info/30 bg-info-soft text-info',
    ];

    /**
     * @var array<string, string>
     */
    private array $classes = self::Defaults;

    public function set(string $key, string $classes): void
    {
        if (isset(self::Defaults[$key])) {
            $this->classes[$key] = $classes;
        }
    }

    public function get(string $key): string
    {
        return $this->classes[$key] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->classes;
    }

    public function isCustomized(): bool
    {
        return $this->classes !== self::Defaults;
    }
}
