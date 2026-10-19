<?php

declare(strict_types=1);

namespace Gcego\Vault\Support;

use Gcego\Chroma\ChromaManager;

/**
 * The framework's own control presets (gcego/chroma), merged once per process:
 * buttons ("primary", "secondary", "destructive") and inputs.
 */
final class VaultStyles
{
    /** @var array<string, string> */
    private array $merged = [];

    public function __construct(private readonly ChromaManager $chroma) {}

    public function button(string $intent = 'secondary', string $extra = ''): string
    {
        $classes = $this->merged['button.'.$intent] ??= (string) $this->chroma->merge($this->chroma->preset('control.button'), $this->chroma->preset('control.button.'.$intent));

        return $extra === '' ? $classes : $classes.' '.$extra;
    }

    public function input(string $extra = ''): string
    {
        $classes = $this->merged['input'] ??= (string) $this->chroma->preset('control.input');

        return $extra === '' ? $classes : $classes.' '.$extra;
    }
}
