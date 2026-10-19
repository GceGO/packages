<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Concerns;

use Gcego\Cli\Exceptions\CliException;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Output\Redactor;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Shared output: --format=text|json, JSON written raw (no ANSI, nothing
 * else on stdout) with a "schema" version, and CLI exceptions turned into
 * their exit codes.
 */
trait RendersOutput
{
    public const int JsonSchema = 1;

    protected function json(): bool
    {
        return $this->format() === 'json';
    }

    protected function format(): string
    {
        $format = $this->hasOption('format') ? $this->option('format') : 'text';

        return is_string($format) && in_array($format, ['text', 'json'], true) ? $format : 'invalid';
    }

    protected function invalidFormat(): ?int
    {
        if ($this->format() !== 'invalid') {
            return null;
        }

        $this->components->error('--format must be "text" or "json".');

        return ExitCode::InvalidInput;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function emit(array $payload): void
    {
        $payload = $this->laravel->make(Redactor::class)->redact(['schema' => self::JsonSchema, ...$payload]);
        $this->output->writeln((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), OutputInterface::OUTPUT_RAW);
    }

    protected function failed(Throwable $exception): int
    {
        $code = $exception instanceof CliException ? $exception->exitCode() : ExitCode::Failure;

        if ($this->json()) {
            $this->emit(['successful' => false, 'error' => $exception->getMessage(), 'exit_code' => $code, 'reason' => ExitCode::descriptions()[$code] ?? 'failure']);
        } else {
            $this->components->error($exception->getMessage());

            if (! $exception instanceof CliException && $this->output->isVerbose()) {
                $this->line($exception->getTraceAsString());
            }
        }

        return $code;
    }
}
