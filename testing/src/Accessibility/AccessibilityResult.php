<?php

declare(strict_types=1);

namespace Gcego\Testing\Accessibility;

/**
 * The violations one audit found, and which engine produced them:
 * "static" (markup rules in PHP) or "axe" (axe-core in a browser).
 */
final readonly class AccessibilityResult
{
    /**
     * @param  list<Violation>  $violations
     * @param  list<string>  $rules  The rules that ran.
     */
    public function __construct(public array $violations, public string $engine = 'static', public array $rules = []) {}

    public function passes(): bool
    {
        return $this->violations === [];
    }

    /**
     * @return list<Violation>
     */
    public function violations(?string $rule = null): array
    {
        return array_values(array_filter($this->violations, static fn (Violation $violation): bool => $rule === null || $violation->rule === $rule));
    }

    /**
     * @param  list<string>  $rules
     */
    public function without(array $rules): self
    {
        return new self(array_values(array_filter($this->violations, static fn (Violation $violation): bool => ! in_array($violation->rule, $rules, true))), $this->engine, array_values(array_diff($this->rules, $rules)));
    }

    /**
     * @return list<string>
     */
    public function formatted(): array
    {
        return array_map(static fn (Violation $violation): string => $violation->format(), $this->violations);
    }

    /**
     * @return array{engine: string, passes: bool, rules: list<string>, violations: list<array{rule: string, message: string, element: string, impact: string}>}
     */
    public function toArray(): array
    {
        return ['engine' => $this->engine, 'passes' => $this->passes(), 'rules' => $this->rules, 'violations' => array_map(static fn (Violation $violation): array => $violation->toArray(), $this->violations)];
    }
}
