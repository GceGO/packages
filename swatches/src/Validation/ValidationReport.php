<?php

declare(strict_types=1);

namespace Gcego\Swatches\Validation;

use Gcego\Swatches\Accessibility\ContrastResult;

/**
 * The outcome of validating one or more themes.
 */
final readonly class ValidationReport
{
    /**
     * @param  list<Issue>  $issues
     * @param  list<ContrastResult>  $contrast
     * @param  list<string>  $themes
     */
    public function __construct(
        public array $issues = [],
        public array $contrast = [],
        public array $themes = [],
    ) {}

    public function merge(self $other): self
    {
        return new self([...$this->issues, ...$other->issues], [...$this->contrast, ...$other->contrast], [...$this->themes, ...$other->themes]);
    }

    public function passes(): bool
    {
        return $this->errors() === [];
    }

    /**
     * @return list<Issue>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->issues, static fn (Issue $issue): bool => $issue->severity === Severity::Error));
    }

    /**
     * @return list<Issue>
     */
    public function warnings(): array
    {
        return array_values(array_filter($this->issues, static fn (Issue $issue): bool => $issue->severity === Severity::Warning));
    }

    public function summary(): string
    {
        return sprintf('%d error(s), %d warning(s) across %d theme(s)', count($this->errors()), count($this->warnings()), count($this->themes));
    }

    /**
     * @return array{passes: bool, themes: list<string>, issues: list<array<string, string|null>>, contrast: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'passes' => $this->passes(),
            'themes' => $this->themes,
            'issues' => array_map(static fn (Issue $issue): array => $issue->toArray(), $this->issues),
            'contrast' => array_map(static fn (ContrastResult $result): array => $result->toArray(), $this->contrast),
        ];
    }
}
