<?php

declare(strict_types=1);

namespace Gcego\Core\Extensions;

use Gcego\Core\Exceptions\ExtensionException;
use Gcego\Core\Support\Identifier;

/**
 * Immutable metadata of a GCEGO extension.
 *
 * - id: a lowercase dotted name ("tables.export").
 * - requires: extensions that must be active and activate first.
 * - optional: extensions that activate first when active.
 * - whenPackages: GCEGO packages that must be active; otherwise the extension
 *   is skipped (not an error). This is how optional cross-package integrations
 *   switch themselves on only when the other package is installed.
 * - capabilities: names reported by Gcego::hasCapability() while active.
 */
final readonly class ExtensionDescriptor
{
    /**
     * @var list<string>
     */
    public array $requires;

    /**
     * @var list<string>
     */
    public array $optional;

    /**
     * @var list<string>
     */
    public array $whenPackages;

    /**
     * @var list<string>
     */
    public array $capabilities;

    /**
     * @param  list<string>  $requires
     * @param  list<string>  $optional
     * @param  list<string>  $whenPackages
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public string $id,
        array $requires = [],
        array $optional = [],
        array $whenPackages = [],
        array $capabilities = [],
        public ?string $description = null,
    ) {
        if (! Identifier::isName($id)) {
            throw ExtensionException::invalidIdentifier($id);
        }

        $this->requires = self::filter($id, $requires, Identifier::isName(...), '"requires" entries must be extension identifiers.');
        $this->optional = self::filter($id, $optional, Identifier::isName(...), '"optional" entries must be extension identifiers.');
        $this->whenPackages = self::filter($id, $whenPackages, Identifier::isPackage(...), '"whenPackages" entries must be Composer package names.');
        $this->capabilities = self::filter($id, $capabilities, Identifier::isName(...), 'capabilities must be lowercase dotted names.');

        if (in_array($id, [...$this->requires, ...$this->optional], true)) {
            throw ExtensionException::invalidMetadata($id, 'an extension cannot depend on itself.');
        }

        $both = array_intersect($this->requires, $this->optional);

        if ($both !== []) {
            throw ExtensionException::invalidMetadata($id, sprintf('"%s" cannot be both required and optional.', implode('", "', $both)));
        }
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * @return array{id: string, requires: list<string>, optional: list<string>, whenPackages: list<string>, capabilities: list<string>, description: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'requires' => $this->requires,
            'optional' => $this->optional,
            'whenPackages' => $this->whenPackages,
            'capabilities' => $this->capabilities,
            'description' => $this->description,
        ];
    }

    /**
     * @param  array<mixed>  $values
     * @param  callable(mixed): bool  $isValid
     * @return list<string>
     */
    private static function filter(string $id, array $values, callable $isValid, string $reason): array
    {
        $valid = [];

        foreach ($values as $value) {
            if (! $isValid($value) || ! is_string($value)) {
                throw ExtensionException::invalidMetadata($id, $reason);
            }

            $valid[] = $value;
        }

        return array_values(array_unique($valid));
    }
}
