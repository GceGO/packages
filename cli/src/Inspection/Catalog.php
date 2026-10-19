<?php

declare(strict_types=1);

namespace Gcego\Cli\Inspection;

use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Registration\RegistrationResult;
use Gcego\Registry\RegistryManager;

/**
 * The discovered definitions, built once per command with gcego/registry's
 * non-strict build — so one invalid file never hides the others from the
 * CLI (the application's registry stays strict). Read-only.
 */
final class Catalog
{
    private ?RegistrationResult $build = null;

    public function __construct(private readonly RegistryManager $registry) {}

    public function build(): RegistrationResult
    {
        return $this->build ??= $this->registry->build(strict: false);
    }

    public function has(string $identity): bool
    {
        return $this->build()->index->has($identity);
    }

    public function descriptor(string $identity): ?DefinitionDescriptor
    {
        return $this->build()->index->get($identity);
    }

    public function definition(string $identity): ?object
    {
        return $this->build()->definitions[$identity] ?? null;
    }

    /**
     * @return list<DefinitionDescriptor>
     */
    public function descriptors(?string $kind = null): array
    {
        $descriptors = [];

        foreach ($this->build()->index as $descriptor) {
            if ($kind === null || $descriptor->kind() === $kind) {
                $descriptors[] = $descriptor;
            }
        }

        usort($descriptors, static fn (DefinitionDescriptor $a, DefinitionDescriptor $b): int => [$a->kind(), $a->key()] <=> [$b->kind(), $b->key()]);

        return $descriptors;
    }

    /**
     * @return list<string>
     */
    public function names(string $kind): array
    {
        return array_map(static fn (DefinitionDescriptor $descriptor): string => $descriptor->name(), $this->descriptors($kind));
    }

    /**
     * Forget the build (after files were generated).
     */
    public function refresh(): void
    {
        $this->build = null;
    }
}
