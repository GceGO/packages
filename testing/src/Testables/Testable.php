<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Closure;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Diagnostics\Failure;
use Throwable;

/**
 * Base for the fluent testables: each assertion builds a Failure with the
 * testable's subject and context, and returns the testable for chaining.
 */
abstract class Testable
{
    public function __construct(protected readonly Verifier $verifier) {}

    /**
     * The noun printed in the failure heading, e.g. "table".
     */
    abstract protected function subject(): string;

    /**
     * Lines that identify what is being tested, e.g. ['Table' => 'subjects'].
     *
     * @return array<string, mixed>
     */
    protected function context(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     */
    protected function check(bool $passed, string $assertion, string $message, array $expected = [], array $actual = [], ?string $suggestion = null): static
    {
        $this->verifier->that($passed, fn (): Failure => new Failure($this->subject(), $assertion, $message, $this->context(), $expected, $actual, $suggestion));

        return $this;
    }

    protected function caught(Closure $callback): ?Throwable
    {
        return $this->verifier->caught($callback);
    }

    protected function export(mixed $value): string
    {
        return $this->verifier->formatter()->scalar($value);
    }

    /**
     * "ClassName: message", or "nothing" when no exception was thrown.
     */
    protected function describe(?Throwable $exception): string
    {
        return $exception === null ? 'nothing' : $exception::class.': '.$exception->getMessage();
    }
}
