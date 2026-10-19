<?php

declare(strict_types=1);

namespace Gcego\Testing\Assertions;

use Closure;
use Gcego\Testing\Diagnostics\AssertionFailureFormatter;
use Gcego\Testing\Diagnostics\Failure;
use PHPUnit\Framework\Assert;
use Throwable;

/**
 * The single place every toolkit assertion passes through.
 *
 * A pass is counted as one PHPUnit assertion; a failure is raised with
 * Assert::fail(), so PHPUnit and Pest report it like any other failure.
 */
final readonly class Verifier
{
    public function __construct(private AssertionFailureFormatter $formatter = new AssertionFailureFormatter) {}

    /**
     * @param  Failure|Closure(): Failure  $failure  Built only when the check fails.
     */
    public function that(bool $passed, Failure|Closure $failure): void
    {
        if ($passed) {
            Assert::assertThat($passed, Assert::isTrue());

            return;
        }

        Assert::fail($this->formatter->format($failure instanceof Closure ? $failure() : $failure));
    }

    /**
     * Runs $callback and returns what it threw, or null.
     */
    public function caught(Closure $callback): ?Throwable
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            return $exception;
        }

        return null;
    }

    /**
     * Walks the getPrevious() chain for the first exception of the class.
     *
     * Livewire and Blade wrap exceptions thrown while rendering.
     *
     * @template T of Throwable
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function rootOf(?Throwable $exception, string $class): ?Throwable
    {
        while ($exception !== null) {
            if ($exception instanceof $class) {
                return $exception;
            }

            $exception = $exception->getPrevious();
        }

        return null;
    }

    public function formatter(): AssertionFailureFormatter
    {
        return $this->formatter;
    }
}
