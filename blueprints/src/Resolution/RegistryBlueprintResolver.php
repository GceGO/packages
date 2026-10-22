<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Resolution;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Contracts\BlueprintResolver;
use Gcego\Blueprints\Exceptions\UnknownBlueprintException;
use Gcego\Blueprints\Registry\BlueprintKind;
use Gcego\Registry\Contracts\RegistryInterface;

/**
 * The application-wide BlueprintResolver: blueprints of kind "blueprint" in
 * gcego/registry, looked up by name ("subjects" is "blueprint:subjects").
 *
 * It keeps no state of its own; discovery, caching and memoization belong to
 * the registry, so refresh() there is reflected here.
 */
final readonly class RegistryBlueprintResolver implements BlueprintResolver
{
    public function __construct(
        private RegistryInterface $registry,
    ) {}

    public function has(string $name): bool
    {
        return $this->registry->has(self::identifier($name));
    }

    /**
     * @throws UnknownBlueprintException
     */
    public function resolve(string $name): BlueprintInterface
    {
        if (! $this->has($name)) {
            throw UnknownBlueprintException::forName($name, $this->names());
        }

        /** @var BlueprintInterface */
        return $this->registry->get(self::identifier($name), BlueprintInterface::class);
    }

    public function names(): array
    {
        return $this->registry->names(BlueprintKind::Id);
    }

    /**
     * @return array<string, BlueprintInterface>
     */
    public function all(): array
    {
        $blueprints = [];

        foreach ($this->names() as $name) {
            $blueprints[$name] = $this->resolve($name);
        }

        return $blueprints;
    }

    private static function identifier(string $name): string
    {
        return BlueprintKind::Id.':'.$name;
    }
}
