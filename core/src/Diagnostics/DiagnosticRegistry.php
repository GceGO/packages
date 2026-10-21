<?php

declare(strict_types=1);

namespace Gcego\Core\Diagnostics;

use Gcego\Core\Contracts\DiagnosticCheck;
use Gcego\Core\Exceptions\DiagnosticException;
use Gcego\Core\Support\Identifier;
use Throwable;

/** Framework-wide checks registered by providers; never request-specific. */
final class DiagnosticRegistry
{
    private const array Reserved = ['php.version', 'php.extensions', 'laravel.version', 'configuration', 'framework.registration', 'framework.boot', 'packages.dependencies', 'packages.components', 'extensions.dependencies', 'diagnostics.custom'];

    /** @var array<string, DiagnosticCheck> */
    private array $checks = [];

    private bool $running = false;

    public function register(DiagnosticCheck $check): void
    {
        $id = $check->id();

        if (! Identifier::isName($id) || in_array($id, self::Reserved, true) || in_array(explode('.', $id)[0], ['php', 'laravel', 'framework', 'configuration', 'packages', 'extensions', 'lifecycle', 'startup', 'capabilities', 'performance', 'security', 'runtime', 'application', 'composer', 'registration', 'diagnostics'], true)) {
            throw new DiagnosticException('Diagnostic check ids must be valid names and must not replace core checks.');
        }

        if (isset($this->checks[$id]) && $this->checks[$id] === $check) {
            return;
        }

        if ($this->running || isset($this->checks[$id])) {
            throw new DiagnosticException('Diagnostic checks cannot be replaced or registered during a diagnostic run.');
        }

        $this->checks[$id] = $check;
    }

    /** @return list<DiagnosticResult> */
    public function run(): array
    {
        if ($this->running) {
            throw new DiagnosticException('Custom diagnostics cannot run recursively.');
        }

        $this->running = true;
        $results = [];
        $checks = $this->checks;
        ksort($checks);

        try {
            foreach ($checks as $id => $check) {
                try {
                    $result = $check->run();
                    $results[] = $result->check === $id
                        ? $result
                        : DiagnosticResult::failed($id, 'The diagnostic returned a different check id.');
                } catch (Throwable $exception) {
                    $results[] = DiagnosticResult::failed($id, sprintf('The check could not run (%s).', DiagnosticRedactor::exceptionClass($exception)));
                }
            }
        } finally {
            $this->running = false;
        }

        return $results;
    }
}
