<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

use Gcego\Blueprints\Contracts\BlueprintDefinition;
use Gcego\Blueprints\Exceptions\DefinitionRegistrationException;

/**
 * The definition types Blueprint::define() accepts.
 *
 * The built-in types are always present, and each one is read by an engine:
 * identity and presentation (Name, Title, Description, Icon), the record and
 * its authorization (Model, Policy), the table, form and infolist engines,
 * pages, actions, relations, widgets, and registry derivation.
 *
 * Capabilities owned by other packages are not built in: navigation,
 * permissions, panels, themes, settings and the like are registered by the
 * package that reads them, usually built on OptionsDefinition or
 * IdentifierListDefinition, from its service provider's register() method:
 *
 *     DefinitionCatalog::register(Navigation::class);
 *
 * A key belongs to exactly one class, so no package can shadow "table" or
 * smuggle in an unvalidated key. Registration is process-wide (definitions
 * are plain PHP values, built before any container exists) and idempotent.
 * Name is the one required built-in type; an extension may register a
 * required type when its package makes the definition mandatory, but only
 * before the first blueprint is defined. Optional types can be added at any time.
 */
final class DefinitionCatalog
{
    public const string KeyPattern = '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/';

    /**
     * @var list<class-string<BlueprintDefinition>>
     */
    public const array BuiltIn = [
        Name::class,
        Title::class,
        Description::class,
        Icon::class,
        Model::class,
        Policy::class,
        Table::class,
        Form::class,
        Infolist::class,
        Pages::class,
        Actions::class,
        BulkActions::class,
        Relations::class,
        Widgets::class,
        Derive::class,
    ];

    /**
     * @var array<string, DefinitionType>|null
     */
    private static ?array $types = null;

    /** Whether a blueprint was compiled, after which required types can no longer be added. */
    private static bool $inUse = false;

    /**
     * Accept a definition class in Blueprint::define().
     *
     * @param  class-string<BlueprintDefinition>  $class
     *
     * @throws DefinitionRegistrationException
     */
    public static function register(string $class, bool $required = false): DefinitionType
    {
        $types = self::types();

        if (! is_subclass_of($class, BlueprintDefinition::class)) {
            throw DefinitionRegistrationException::notADefinition($class);
        }

        $key = $class::definitionKey();

        if (strlen($key) > 64 || preg_match(self::KeyPattern, $key) !== 1) {
            throw DefinitionRegistrationException::invalidKey($class, $key);
        }

        if (isset($types[$key])) {
            if ($types[$key]->class !== $class) {
                throw DefinitionRegistrationException::keyTaken($key, $types[$key]->class, $class);
            }

            return $types[$key];
        }

        if ($required && self::$inUse) {
            throw DefinitionRegistrationException::tooLate($class);
        }

        return self::$types[$key] = new DefinitionType($key, $class, $required);
    }

    /**
     * Note that blueprints exist: from now on a newly required type would
     * silently invalidate them, so required types are refused.
     *
     * @internal Called by Blueprint::define().
     */
    public static function markInUse(): void
    {
        self::$inUse = true;
    }

    public static function has(string $key): bool
    {
        return isset(self::types()[$key]);
    }

    public static function find(string $key): ?DefinitionType
    {
        return self::types()[$key] ?? null;
    }

    /**
     * @return array<string, DefinitionType> Built-in types first, then extensions in registration order.
     */
    public static function all(): array
    {
        return self::types();
    }

    /**
     * @return list<DefinitionType>
     */
    public static function required(): array
    {
        return array_values(array_filter(self::types(), static fn (DefinitionType $type): bool => $type->required));
    }

    /**
     * Forget extension types (built-ins stay). For test isolation only.
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$types = null;
        self::$inUse = false;
    }

    /**
     * @return array<string, DefinitionType>
     */
    private static function types(): array
    {
        if (self::$types === null) {
            self::$types = [];

            foreach (self::BuiltIn as $class) {
                self::$types[$class::definitionKey()] = new DefinitionType($class::definitionKey(), $class, $class === Name::class);
            }
        }

        return self::$types;
    }
}
