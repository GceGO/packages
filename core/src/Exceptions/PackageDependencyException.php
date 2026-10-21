<?php

declare(strict_types=1);

namespace Gcego\Core\Exceptions;

use LogicException;

class PackageDependencyException extends LogicException implements GcegoException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, public readonly string $diagnosticCode = 'packages.dependencies')
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param  list<string>  $missing
     */
    public static function missing(string $package, array $missing): self
    {
        return new self(sprintf(
            'GCEGO package "%s" requires %s, which %s not registered. Install it with "composer require %s" and make sure its service provider is loaded.',
            $package,
            self::quote($missing),
            count($missing) === 1 ? 'is' : 'are',
            implode(' ', $missing),
        ), diagnosticCode: 'packages.dependencies.missing');
    }

    /**
     * @param  list<string>  $disabled
     */
    public static function disabled(string $package, array $disabled): self
    {
        return new self(sprintf(
            'GCEGO package "%s" requires %s, which %s disabled in gcego.packages.disabled. Disable "%s" as well or re-enable its dependencies.',
            $package,
            self::quote($disabled),
            count($disabled) === 1 ? 'is' : 'are',
            $package,
        ), diagnosticCode: 'packages.dependencies.disabled');
    }

    /**
     * @param  list<string>  $cycle  The cycle, starting and ending with the same package.
     */
    public static function circular(array $cycle): self
    {
        return new self('Circular GCEGO package dependency: '.implode(' -> ', $cycle).'.', diagnosticCode: 'packages.dependencies.circular');
    }

    /**
     * @param  list<string>  $ids
     */
    private static function quote(array $ids): string
    {
        return implode(', ', array_map(static fn (string $id): string => '"'.$id.'"', $ids));
    }
}
