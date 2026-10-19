<?php

declare(strict_types=1);

namespace Gcego\Security;

use Gcego\Components\Property;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Exceptions\InvalidPermissionException;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Permissions\Permissions;
use Gcego\Security\Requirements\Ability;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Security\Requirements\PasswordConfirmed;
use Gcego\Security\Requirements\PublicAccess;
use Gcego\Security\Requirements\TenantMember;
use Gcego\Security\Requirements\VerifiedEmail;
use Illuminate\Database\Eloquent\Model;

/**
 * Requirements in the gcego/components Property syntax:
 *
 *     new Security(
 *         new Property(Security::Permission, 'subjects.view'),
 *     )
 *
 *     new Security(
 *         new Property(Security::Permissions, ['members.view', 'members.invite']),
 *         new Property(Security::Tenant, true),        // tenant permissions + verified membership
 *         new Property(Security::Verified, true),
 *     )
 *
 * Equivalent to the requirement nodes it lists; all must allow.
 */
final readonly class Security implements Requirement
{
    /** One permission (string) or several, all required (list). */
    public const string Permission = 'permission';

    /** Several permissions, all required. */
    public const string Permissions = 'permissions';

    /** Several permissions, any one suffices. */
    public const string AnyPermission = 'any_permission';

    /** A Gate ability or policy method. */
    public const string Ability = 'ability';

    /** With Ability: the model class for class-level policy checks. */
    public const string Model = 'model';

    /** true: permissions are tenant permissions and a verified membership is required. */
    public const string Tenant = 'tenant';

    /** true: anyone, guests included. */
    public const string Public = 'public';

    /** Required guard name (implies authenticated). */
    public const string Guard = 'guard';

    /** true: a verified email address. */
    public const string Verified = 'verified';

    /** Seconds within which the password must have been confirmed. */
    public const string PasswordConfirmed = 'password_confirmed';

    /**
     * @var list<Requirement>
     */
    public array $requirements;

    /**
     * @throws InvalidPermissionException
     */
    public function __construct(Property|Requirement ...$items)
    {
        $values = [];
        $requirements = [];

        foreach ($items as $item) {
            if ($item instanceof Requirement) {
                $requirements[] = $item;

                continue;
            }

            if (array_key_exists($item->key, $values)) {
                throw InvalidPermissionException::invalid('Security', sprintf('property "%s" is declared twice.', $item->key));
            }

            $values[$item->key] = $item->value;
        }

        $known = [self::Permission, self::Permissions, self::AnyPermission, self::Ability, self::Model, self::Tenant, self::Public, self::Guard, self::Verified, self::PasswordConfirmed];

        if (($unknown = array_diff(array_keys($values), $known)) !== []) {
            throw InvalidPermissionException::invalid('Security', sprintf('unknown property "%s". Known: %s.', (string) reset($unknown), implode(', ', $known)));
        }

        $tenant = self::flag($values, self::Tenant);
        $all = [...self::names($values, self::Permission), ...self::names($values, self::Permissions)];

        if (self::flag($values, self::Public)) {
            $requirements[] = new PublicAccess;
        }

        if (isset($values[self::Guard])) {
            $requirements[] = new Authenticated(is_string($values[self::Guard]) ? $values[self::Guard] : throw InvalidPermissionException::invalid('Security', 'guard must be a string.'));
        }

        if ($tenant) {
            $requirements[] = new TenantMember;
        }

        if (self::flag($values, self::Verified)) {
            $requirements[] = new VerifiedEmail;
        }

        if (isset($values[self::PasswordConfirmed])) {
            $requirements[] = new PasswordConfirmed(is_int($values[self::PasswordConfirmed]) ? $values[self::PasswordConfirmed] : throw InvalidPermissionException::invalid('Security', 'password_confirmed must be a number of seconds.'));
        }

        foreach ($all as $name) {
            $requirements[] = new Permission($name, $tenant);
        }

        if (($any = self::names($values, self::AnyPermission)) !== []) {
            $permissions = Permissions::any(...$any);
            $requirements[] = $tenant ? $permissions->inTenant() : $permissions;
        }

        if (isset($values[self::Ability])) {
            $model = $values[self::Model] ?? null;
            $requirements[] = new Ability(
                is_string($values[self::Ability]) ? $values[self::Ability] : throw InvalidPermissionException::invalid('Security', 'ability must be a string.'),
                is_string($model) && is_subclass_of($model, Model::class) ? $model : ($model === null ? null : throw InvalidPermissionException::invalid('Security', 'model must be an Eloquent model class.')),
            );
        } elseif (isset($values[self::Model])) {
            throw InvalidPermissionException::invalid('Security', 'model is only meaningful with ability.');
        }

        if ($requirements === []) {
            throw InvalidPermissionException::invalid('Security', 'declare at least one requirement.');
        }

        $this->requirements = $requirements;
    }

    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision
    {
        return $engine->requirements($this->requirements, $resource, $context);
    }

    public function describe(): string
    {
        return implode('; ', array_map(static fn (Requirement $requirement): string => $requirement->describe(), $this->requirements));
    }

    /**
     * @return array{requirements: list<string>}
     */
    public function normalize(): array
    {
        return ['requirements' => array_map(static fn (Requirement $requirement): string => $requirement->describe(), $this->requirements)];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function flag(array $values, string $key): bool
    {
        $value = $values[$key] ?? false;

        return is_bool($value) ? $value : throw InvalidPermissionException::invalid('Security', sprintf('%s must be true or false.', $key));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private static function names(array $values, string $key): array
    {
        $value = $values[$key] ?? [];
        $names = is_string($value) ? [$value] : $value;

        if (! is_array($names) || array_filter($names, static fn (mixed $name): bool => ! is_string($name)) !== []) {
            throw InvalidPermissionException::invalid('Security', sprintf('%s must be a permission name or a list of them.', $key));
        }

        /** @var list<string> $names */
        return array_values($names);
    }
}
