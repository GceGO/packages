<?php

declare(strict_types=1);

namespace Gcego\Core\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when framework boot fails. The original failure is preserved as the previous exception.
 */
class BootstrapException extends RuntimeException implements GcegoException
{
    public static function failed(Throwable $previous): self
    {
        return new self('GCEGO failed to boot: '.$previous->getMessage(), 0, $previous);
    }

    public static function packageFailed(string $package, Throwable $previous): self
    {
        return new self(sprintf('GCEGO package "%s" failed to boot: %s', $package, $previous->getMessage()), 0, $previous);
    }

    public static function extensionFailed(string $extension, Throwable $previous): self
    {
        return new self(sprintf('GCEGO extension "%s" failed to activate: %s', $extension, $previous->getMessage()), 0, $previous);
    }

    /**
     * @param  list<string>  $failures
     */
    public static function environment(array $failures): self
    {
        return new self(sprintf(
            "GCEGO environment checks failed:\n- %s\n\nFix the environment, or set gcego.environment_checks to false to skip these checks.",
            implode("\n- ", $failures),
        ));
    }
}
