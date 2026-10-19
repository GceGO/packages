<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use BackedEnum;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Validation\ValidationError;
use Gcego\Testing\Assertions\Verifier;
use UnitEnum;

/**
 * Definition checks for gcego/analytics metrics, datasets, dimensions and
 * other analytics nodes: component validation plus each definition's own
 * problems() (unknown fields, aggregates a field does not allow, formula
 * references…).
 *
 * Only the definition API is covered: gcego/analytics' report and query
 * engine is still being built, and the toolkit does not guess its API.
 */
final class AnalyticsTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly ComponentInterface $definition)
    {
        parent::__construct($verifier);
    }

    public function assertValid(): self
    {
        $problems = $this->problems();

        return $this->check($problems === [], 'assertValid()', 'Expected the analytics definition to be valid.', ['problems' => []], ['problems' => $problems]);
    }

    /**
     * The definition reports a problem containing $text.
     */
    public function assertProblem(string $text): self
    {
        $problems = $this->problems();
        $matching = array_filter($problems, static fn (string $problem): bool => str_contains($problem, $text));

        return $this->check($matching !== [], sprintf('assertProblem(%s)', $this->export($text)), 'Expected the definition to report the problem.', ['problem' => $text], ['problems' => $problems]);
    }

    /**
     * A metric's kind: a MetricKind case or its value ("count", "ratio"…).
     */
    public function assertKind(UnitEnum|string $kind): self
    {
        $actual = method_exists($this->definition, 'kind') ? $this->definition->kind() : null;
        $expected = $kind instanceof BackedEnum ? $kind->value : ($kind instanceof UnitEnum ? $kind->name : $kind);
        $value = $actual instanceof BackedEnum ? $actual->value : ($actual instanceof UnitEnum ? $actual->name : $actual);

        return $this->check($value === $expected, sprintf('assertKind(%s)', $this->export($expected)), 'Expected the metric kind.', ['kind' => $expected], ['kind' => $value]);
    }

    /**
     * A derived or composite metric depends on exactly these metrics.
     *
     * @param  list<string>  $metrics
     */
    public function assertDependsOn(array $metrics): self
    {
        $actual = method_exists($this->definition, 'dependencies') ? $this->definition->dependencies() : [];
        $actual = is_array($actual) ? array_values(array_map(static fn (mixed $name): string => is_scalar($name) ? (string) $name : get_debug_type($name), $actual)) : [];
        sort($metrics);
        sort($actual);

        return $this->check($actual === $metrics, 'assertDependsOn('.$this->export($metrics).')', 'Expected the metric dependencies.', ['dependencies' => $metrics], ['dependencies' => $actual]);
    }

    protected function subject(): string
    {
        return 'analytics';
    }

    protected function context(): array
    {
        return ['Definition' => $this->definition::class, 'Name' => $this->definition->get('name')];
    }

    /**
     * @return list<string>
     */
    private function problems(): array
    {
        $problems = array_map(static fn (ValidationError $error): string => $error->format(), $this->definition->validate()->errors());

        if (method_exists($this->definition, 'problems')) {
            foreach ((array) $this->definition->problems() as $problem) {
                $problems[] = is_scalar($problem) ? (string) $problem : get_debug_type($problem);
            }
        }

        return array_values($problems);
    }
}
