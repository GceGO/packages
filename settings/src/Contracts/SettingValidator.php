<?php

declare(strict_types=1);

namespace Gcego\Settings\Contracts;

use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\Validation\SettingValidationResult;

/**
 * Casts and validates submitted values on the server: types, the
 * definitions' Laravel rules, options and enums, media ownership, and
 * dependencies between settings. Never trusts the browser.
 */
interface SettingValidator
{
    /**
     * @param  array<string, mixed>  $values  Untrusted values by setting key.
     * @param  array<string, SettingDefinition>  $definitions  The definitions of those keys.
     */
    public function validate(array $values, array $definitions, ScopeContext $context): SettingValidationResult;
}
