<?php

declare(strict_types=1);

namespace Gcego\Settings\Scopes;

use Gcego\Settings\Definitions\SettingDefinition;

/**
 * Which stored levels can supply a setting's value in a context, in order:
 * the setting's precedence (its own, or gcego-settings.scopes.precedence),
 * limited to the scopes the setting allows and the levels the context
 * includes. The definition default comes after all of them.
 *
 * A level the setting does not allow is never consulted, so a tenant row
 * can never override a platform-only setting, even if one were written
 * outside this package.
 */
final class ScopePrecedenceResolver
{
    /**
     * @return list<array{scope: SettingScope, key: string}>
     */
    public function candidates(SettingDefinition $definition, ScopeContext $context): array
    {
        $candidates = [];

        foreach ($definition->precedence as $scope) {
            $key = $context->keyOf($scope);

            if ($key !== null && $context->includes($scope)) {
                $candidates[] = ['scope' => $scope, 'key' => $key];
            }
        }

        return $candidates;
    }
}
