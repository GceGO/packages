<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Classes\ClassList;
use Gcego\Chroma\Diagnostics\ChromaInspector;
use Gcego\Chroma\Exceptions\InvalidSlotException;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Support\Reports;
use Illuminate\Contracts\Container\Container;

/**
 * The classes gcego/chroma actually resolves for a component's slot,
 * through all five layers (preset, theme, slot, app override, instance).
 *
 * Class order is not asserted, except by assertClassesInOrder(): only the
 * merge result is contractual.
 */
final class StylingTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly Container $container, private readonly ComponentInterface $component, private readonly ?string $theme = null)
    {
        parent::__construct($verifier);
    }

    public function theme(string $theme): self
    {
        return new self($this->verifier, $this->container, $this->component, $theme);
    }

    /**
     * @return list<string>
     */
    public function classes(string $slot): array
    {
        return $this->manager()->classList($this->component, $slot, $this->theme)->all();
    }

    /**
     * @param  string|list<string>  $classes
     */
    public function assertHasClasses(string $slot, string|array $classes): self
    {
        $expected = ClassList::from($classes)->all();
        $actual = $this->classes($slot);
        $missing = array_values(array_diff($expected, $actual));

        return $this->check($missing === [], sprintf('assertHasClasses(%s, %s)', $this->export($slot), $this->export($expected)), sprintf('Expected the slot [%s] to include the classes.', $slot), ['includes' => $expected], ['classes' => $actual, 'missing' => $missing]);
    }

    /**
     * @param  string|list<string>  $classes
     */
    public function assertLacksClasses(string $slot, string|array $classes): self
    {
        $unexpected = array_values(array_intersect(ClassList::from($classes)->all(), $this->classes($slot)));

        return $this->check($unexpected === [], sprintf('assertLacksClasses(%s, %s)', $this->export($slot), $this->export($classes)), sprintf('Expected the slot [%s] not to include the classes.', $slot), ['excludes' => $classes], ['present' => $unexpected]);
    }

    /**
     * Exactly these classes, in any order.
     *
     * @param  string|list<string>  $classes
     */
    public function assertClasses(string $slot, string|array $classes): self
    {
        $expected = ClassList::from($classes)->all();
        $actual = $this->classes($slot);
        $sortedExpected = $expected;
        $sortedActual = $actual;
        sort($sortedExpected);
        sort($sortedActual);

        return $this->check($sortedExpected === $sortedActual, sprintf('assertClasses(%s, %s)', $this->export($slot), $this->export($expected)), sprintf('Expected the slot [%s] to resolve to exactly these classes.', $slot), ['classes' => $expected], ['classes' => $actual, 'missing' => array_values(array_diff($expected, $actual)), 'extra' => array_values(array_diff($actual, $expected))]);
    }

    /**
     * Exactly these classes in this order (only where order is meaningful).
     *
     * @param  string|list<string>  $classes
     */
    public function assertClassesInOrder(string $slot, string|array $classes): self
    {
        $expected = ClassList::from($classes)->all();
        $actual = $this->classes($slot);

        return $this->check($expected === $actual, sprintf('assertClassesInOrder(%s, %s)', $this->export($slot), $this->export($expected)), sprintf('Expected the slot [%s] to resolve to these classes in order.', $slot), ['classes' => $expected], ['classes' => $actual]);
    }

    /**
     * The slot name is not declared for this component (strict styling).
     */
    public function assertUnknownSlot(string $slot): self
    {
        $exception = $this->caught(fn () => $this->classes($slot));

        return $this->check($exception instanceof InvalidSlotException, sprintf('assertUnknownSlot(%s)', $this->export($slot)), sprintf('Expected [%s] to be refused as an unknown slot.', $slot), ['exception' => InvalidSlotException::class], ['exception' => $this->describe($exception)]);
    }

    public function assertHealthy(): self
    {
        $problems = Reports::problems($this->container->make(ChromaInspector::class)->inspect($this->component, $this->theme));

        return $this->check($problems === [], 'assertHealthy()', 'Expected the styling inspector to report no errors for the component.', ['errors' => []], ['errors' => $problems]);
    }

    protected function subject(): string
    {
        return 'styling';
    }

    protected function context(): array
    {
        return ['Component' => $this->component::class, 'Theme' => $this->theme ?? '(default)'];
    }

    private function manager(): ChromaManager
    {
        return $this->container->make(ChromaManager::class);
    }
}
