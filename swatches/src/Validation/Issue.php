<?php

declare(strict_types=1);

namespace Gcego\Swatches\Validation;

/**
 * One validation finding. Messages never contain raw input (see Support\Safe).
 */
final readonly class Issue
{
    public const string Contrast = 'contrast';

    public const string BrokenReference = 'broken-reference';

    public const string CircularReference = 'circular-reference';

    public const string Inheritance = 'inheritance';

    public const string OutOfGamut = 'out-of-gamut';

    public const string IndistinctSeries = 'indistinct-series';

    public const string Apca = 'apca';

    public const string ColorVision = 'color-vision';

    public function __construct(
        public Severity $severity,
        public string $code,
        public string $theme,
        public string $message,
        public ?string $mode = null,
        public ?string $token = null,
        public ?string $suggestion = null,
    ) {}

    /**
     * @return array{severity: string, code: string, theme: string, mode: string|null, token: string|null, message: string, suggestion: string|null}
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity->value,
            'code' => $this->code,
            'theme' => $this->theme,
            'mode' => $this->mode,
            'token' => $this->token,
            'message' => $this->message,
            'suggestion' => $this->suggestion,
        ];
    }
}
