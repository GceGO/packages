<?php

declare(strict_types=1);

namespace Gcego\Core\Packages;

use Gcego\Core\Exceptions\PackageRegistrationException;
use Gcego\Core\Support\Identifier;

/**
 * Immutable framework-level metadata of a GCEGO package.
 *
 * - id: the Composer package name ("gcego/tables").
 * - version: omit to read it from Composer's installed metadata.
 * - requires: GCEGO packages that must be registered and boot first.
 * - optional: GCEGO packages that boot first when present.
 * - capabilities: names other packages can test with Gcego::hasCapability().
 *
 * Composer-level library dependencies (such as gcego/components) belong in
 * composer.json, not here: Composer resolves installation and versions.
 */
final readonly class PackageDescriptor
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
    public array $capabilities;

    /**
     * @param  list<string>  $requires
     * @param  list<string>  $optional
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public string $id,
        public ?string $version = null,
        array $requires = [],
        array $optional = [],
        array $capabilities = [],
        public ?string $description = null,
    ) {
        if (! Identifier::isPackage($id)) {
            throw PackageRegistrationException::invalidIdentifier($id);
        }

        if ($version !== null && trim($version) === '') {
            throw PackageRegistrationException::invalidMetadata($id, 'the version must not be empty.');
        }

        $this->requires = self::packages($id, 'requires', $requires);
        $this->optional = self::packages($id, 'optional', $optional);
        $this->capabilities = self::names($id, $capabilities);

        if (in_array($id, [...$this->requires, ...$this->optional], true)) {
            throw PackageRegistrationException::invalidMetadata($id, 'a package cannot depend on itself.');
        }

        $both = array_intersect($this->requires, $this->optional);

        if ($both !== []) {
            throw PackageRegistrationException::invalidMetadata($id, sprintf('"%s" cannot be both required and optional.', implode('", "', $both)));
        }
    }

    public function withVersion(?string $version): self
    {
        return new self($this->id, $version, $this->requires, $this->optional, $this->capabilities, $this->description);
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * @return array{id: string, version: string|null, requires: list<string>, optional: list<string>, capabilities: list<string>, description: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'requires' => $this->requires,
            'optional' => $this->optional,
            'capabilities' => $this->capabilities,
            'description' => $this->description,
        ];
    }

    /**
     * @param  array<mixed>  $ids
     * @return list<string>
     */
    private static function packages(string $id, string $field, array $ids): array
    {
        $valid = [];

        foreach ($ids as $dependency) {
            if (! Identifier::isPackage($dependency) || ! is_string($dependency)) {
                throw PackageRegistrationException::invalidMetadata($id, sprintf('every "%s" entry must be a Composer package name.', $field));
            }

            $valid[] = $dependency;
        }

        return array_values(array_unique($valid));
    }

    /**
     * @param  array<mixed>  $names
     * @return list<string>
     */
    private static function names(string $id, array $names): array
    {
        $valid = [];

        foreach ($names as $name) {
            if (! Identifier::isName($name) || ! is_string($name)) {
                throw PackageRegistrationException::invalidMetadata($id, 'capabilities must be lowercase dotted names such as "tables" or "security.authorization".');
            }

            $valid[] = $name;
        }

        return array_values(array_unique($valid));
    }
}
