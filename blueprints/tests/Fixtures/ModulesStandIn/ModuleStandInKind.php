<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures\ModulesStandIn;

use Gcego\Registry\Contracts\DefinitionKind;
use InvalidArgumentException;

/**
 * The "module" kind, as Gcego\Modules\Registry\ModuleKind declares it.
 */
final readonly class ModuleStandInKind implements DefinitionKind
{
    public const string Id = 'module';

    public function id(): string
    {
        return self::Id;
    }

    public function contract(): string
    {
        return ModuleStandIn::class;
    }

    public function name(object $definition): string
    {
        return $definition instanceof ModuleStandIn ? $definition->name : throw new InvalidArgumentException('Not a module.');
    }

    public function validate(object $definition): array
    {
        return [];
    }

    public function metadata(object $definition): array
    {
        return [];
    }
}
