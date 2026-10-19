<?php

declare(strict_types=1);

namespace Gcego\Navigation\Breadcrumbs;

use Gcego\Tables\Support\SafeUrl;

/**
 * One breadcrumb. Unsafe URLs are dropped (the crumb becomes plain text).
 */
final readonly class Breadcrumb
{
    public ?string $url;

    public function __construct(public string $label, ?string $url = null, public bool $current = false)
    {
        $this->url = $url === null ? null : SafeUrl::link($url);
    }

    public function asCurrent(bool $current = true): self
    {
        return new self($this->label, $this->url, $current);
    }

    /**
     * @return array{label: string, url: string|null, current: bool}
     */
    public function toArray(): array
    {
        return ['label' => $this->label, 'url' => $this->url, 'current' => $this->current];
    }
}
