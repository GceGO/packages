<?php

declare(strict_types=1);

namespace Gcego\Settings\Scopes;

/**
 * Where a value is stored. "Institution" settings are tenant settings: an
 * institution is the gcego/security tenant of a request, so it is one
 * security boundary and one scope, never two.
 *
 * - Global: the whole platform (scope key "");
 * - Panel:  one gcego/panels panel (scope key: the panel name);
 * - Tenant: one tenant (scope key: Tenant::id(), e.g. "institution:12");
 * - User:   one person's preference (scope key: morph class and key).
 */
enum SettingScope: string
{
    case Global = 'global';
    case Panel = 'panel';
    case Tenant = 'tenant';
    case User = 'user';

    /**
     * @param  list<mixed>  $values
     * @return list<self>
     */
    public static function list(array $values): array
    {
        $scopes = [];

        foreach ($values as $value) {
            $scope = $value instanceof self ? $value : (is_string($value) ? self::tryFrom($value) : null);

            if ($scope !== null && ! in_array($scope, $scopes, true)) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }

    public function label(): string
    {
        return (string) __('gcego-settings::messages.scopes.'.$this->value);
    }
}
