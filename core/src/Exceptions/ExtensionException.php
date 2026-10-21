<?php

declare(strict_types=1);

namespace Gcego\Core\Exceptions;

use LogicException;

class ExtensionException extends LogicException implements GcegoException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, public readonly string $diagnosticCode = 'extensions.dependencies')
    {
        parent::__construct($message, $code, $previous);
    }

    public static function invalidIdentifier(string $id): self
    {
        return new self(sprintf(
            'Invalid GCEGO extension identifier "%s". Use lowercase dotted names, for example "tables.export".',
            $id,
        ));
    }

    public static function invalidMetadata(string $id, string $reason): self
    {
        return new self(sprintf('Invalid metadata for GCEGO extension "%s": %s', $id, $reason));
    }

    public static function duplicate(string $id, string $existingClass, string $newClass): self
    {
        return new self(sprintf(
            'GCEGO extension "%s" is already registered by %s; %s cannot replace it.',
            $id,
            $existingClass,
            $newClass,
        ));
    }

    public static function notRegistered(string $id): self
    {
        return new self(sprintf('GCEGO extension "%s" is not registered.', $id));
    }

    public static function duringInitialization(string $id): self
    {
        return new self(sprintf(
            'GCEGO extension "%s" cannot be registered while extensions are initializing. Register it before initialization starts.',
            $id,
        ));
    }

    public static function afterBoot(string $id): self
    {
        return new self(sprintf(
            'GCEGO extension "%s" was registered after the framework booted. Register extensions from a service provider\'s register() or boot() method.',
            $id,
        ));
    }

    /**
     * @param  list<string>  $missing
     */
    public static function missing(string $extension, array $missing): self
    {
        return new self(sprintf(
            'GCEGO extension "%s" requires the extension(s) %s, which are not registered.',
            $extension,
            implode(', ', $missing),
        ), diagnosticCode: 'extensions.dependencies.missing');
    }

    public static function requiresDisabled(string $extension, string $dependency): self
    {
        return new self(sprintf(
            'GCEGO extension "%s" requires "%s", which is disabled in gcego.extensions.disabled. Disable "%s" as well or re-enable "%s".',
            $extension,
            $dependency,
            $extension,
            $dependency,
        ), diagnosticCode: 'extensions.dependencies.disabled');
    }

    /**
     * @param  list<string>  $cycle
     */
    public static function circular(array $cycle): self
    {
        return new self('Circular GCEGO extension dependency: '.implode(' -> ', $cycle).'.', diagnosticCode: 'extensions.dependencies.circular');
    }

    /**
     * @param  list<string>  $providers  Package and extension identifiers.
     */
    public static function exclusiveCapability(string $capability, array $providers): self
    {
        return new self(sprintf(
            'Capability "%s" is exclusive but is provided by %s. Disable all but one provider in gcego.packages.disabled or gcego.extensions.disabled.',
            $capability,
            implode(', ', $providers),
        ), diagnosticCode: 'extensions.capabilities.exclusive');
    }
}
