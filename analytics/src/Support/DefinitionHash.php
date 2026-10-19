<?php

declare(strict_types=1);

namespace Gcego\Analytics\Support;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Security\Contracts\Requirement;

/**
 * A stable fingerprint of a definition tree (classes, properties,
 * requirements, children), for cache keys: changing a definition never
 * serves results computed with the old one.
 */
final class DefinitionHash
{
    public static function of(ComponentInterface $component): string
    {
        return substr(hash('sha256', json_encode(self::tree($component), JSON_THROW_ON_ERROR)), 0, 20);
    }

    /**
     * @return array<string, mixed>
     */
    private static function tree(ComponentInterface $component): array
    {
        $properties = $component->properties()->toArray();
        ksort($properties);

        return [
            'class' => $component::class,
            'properties' => $properties,
            'requirements' => array_map(static fn (mixed $node): string => $node::class.':'.json_encode(get_object_vars($node)), array_values(array_filter($component->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement))),
            'children' => array_map(self::tree(...), array_values($component->children()->all())),
        ];
    }
}
