<?php

declare(strict_types=1);

namespace Gcego\Swatches\Accessibility;

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Tokens\ContrastRequirement;

/**
 * One checked foreground/background token pair in one mode.
 */
final readonly class ContrastResult
{
    public function __construct(
        public string $foreground,
        public string $background,
        public ContrastRequirement $requirement,
        public string $purpose,
        public bool $dark,
        public Color $foregroundColor,
        public Color $backgroundColor,
        public float $ratio,
        public ?Color $suggestion = null,
        public float $apca = 0.0,
    ) {}

    /**
     * Whether the pair also reaches the APCA level this package maps to its requirement (advisory).
     */
    public function passesApca(): bool
    {
        return abs($this->apca) >= Apca::minimum($this->requirement);
    }

    public function passes(): bool
    {
        return $this->ratio >= $this->requirement->minimum();
    }

    public function mode(): string
    {
        return $this->dark ? 'dark' : 'light';
    }

    /**
     * @return array{foreground: string, background: string, mode: string, purpose: string, requirement: string, minimum: float, ratio: float, passes: bool, foreground_color: string, background_color: string, suggestion: string|null, apca: float, apca_minimum: float, passes_apca: bool}
     */
    public function toArray(): array
    {
        return [
            'foreground' => $this->foreground,
            'background' => $this->background,
            'mode' => $this->mode(),
            'purpose' => $this->purpose,
            'requirement' => $this->requirement->value,
            'minimum' => $this->requirement->minimum(),
            'ratio' => floor($this->ratio * 100) / 100,
            'passes' => $this->passes(),
            'foreground_color' => $this->foregroundColor->toHex(),
            'background_color' => $this->backgroundColor->toHex(),
            'suggestion' => $this->suggestion?->toHex(),
            'apca' => round($this->apca, 1),
            'apca_minimum' => Apca::minimum($this->requirement),
            'passes_apca' => $this->passesApca(),
        ];
    }
}
