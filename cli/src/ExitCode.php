<?php

declare(strict_types=1);

namespace Gcego\Cli;

/**
 * The exit codes of every gcego CLI command (a contract for CI scripts).
 *
 * 0, 1 and 2 follow Symfony Console (success, failure, invalid usage);
 * the others distinguish what CI usually needs to react to.
 */
final class ExitCode
{
    /** Done. */
    public const int Success = 0;

    /** An unexpected internal error. */
    public const int Failure = 1;

    /** Invalid input: a name, option or identifier was rejected. */
    public const int InvalidInput = 2;

    /** Definitions (or generated code) failed validation. */
    public const int ValidationFailed = 3;

    /** A target file exists and differs; nothing was written (use --force). */
    public const int FileConflict = 4;

    /** A required package, model, module or panel is missing. */
    public const int MissingDependency = 5;

    /** At least one diagnostic check failed. */
    public const int DiagnosticFailure = 6;

    /**
     * @return array<int, string>
     */
    public static function descriptions(): array
    {
        return [
            self::Success => 'success',
            self::Failure => 'internal failure',
            self::InvalidInput => 'invalid input',
            self::ValidationFailed => 'validation failed',
            self::FileConflict => 'file conflict',
            self::MissingDependency => 'missing dependency',
            self::DiagnosticFailure => 'diagnostic failure',
        ];
    }
}
