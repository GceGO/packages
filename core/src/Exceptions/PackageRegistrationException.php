<?php

declare(strict_types=1);

namespace Gcego\Core\Exceptions;

use LogicException;

class PackageRegistrationException extends LogicException implements GcegoException
{
    public static function invalidIdentifier(string $id): self
    {
        return new self(sprintf(
            'Invalid GCEGO package identifier "%s". Use the Composer package name, for example "gcego/tables".',
            $id,
        ));
    }

    public static function invalidMetadata(string $id, string $reason): self
    {
        return new self(sprintf('Invalid metadata for GCEGO package "%s": %s', $id, $reason));
    }

    public static function duplicate(string $id, string $existingClass, string $newClass): self
    {
        return new self(sprintf(
            'GCEGO package "%s" is already registered by %s; %s cannot replace it.',
            $id,
            $existingClass,
            $newClass,
        ));
    }

    public static function notRegistered(string $id): self
    {
        return new self(sprintf('GCEGO package "%s" is not registered.', $id));
    }

    public static function duringInitialization(string $id): self
    {
        return new self(sprintf(
            'GCEGO package "%s" cannot be registered while packages are initializing. Register it before initialization starts.',
            $id,
        ));
    }

    public static function afterBoot(string $id): self
    {
        return new self(sprintf(
            'GCEGO package "%s" was registered after package registration was locked. Register packages from a service provider\'s register() or boot() method.',
            $id,
        ));
    }
}
