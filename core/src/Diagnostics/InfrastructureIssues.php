<?php

declare(strict_types=1);

namespace Gcego\Core\Diagnostics;

/** Bounded startup audit. Stores safe codes and component identifiers, never exception messages. */
final class InfrastructureIssues
{
    /** @var list<array{code: string, component: string|null, exception: string|null}> */
    private array $issues = [];

    public function record(string $code, ?string $component = null, ?\Throwable $exception = null): void
    {
        $this->issues[] = ['code' => $code, 'component' => $component, 'exception' => $exception === null ? null : DiagnosticRedactor::exceptionClass($exception)];
        if (count($this->issues) > 100) {
            array_shift($this->issues);
        }
    }

    /** @return list<array{code: string, component: string|null, exception: string|null}> */
    public function all(): array
    {
        return $this->issues;
    }
}
