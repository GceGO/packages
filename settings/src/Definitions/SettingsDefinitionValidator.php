<?php

declare(strict_types=1);

namespace Gcego\Settings\Definitions;

use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\Setting;
use Gcego\Settings\SettingsGroup;
use Gcego\Settings\SettingsPage;
use Gcego\Settings\Types\SettingCaster;
use Gcego\Settings\Types\SettingType;

/**
 * Structural checks with actionable messages.
 *
 * problemsOf() runs as the group's gcego/morfaw constraint (so the
 * registry refuses an invalid group with its path): duplicate keys and
 * sections, enum and type mismatches, defaults that do not fit their type,
 * options and fields that do not fit the type, precedence that is not the
 * allowed scopes, self-references.
 *
 * problemsAcross() checks what one group cannot see: keys declared in two
 * groups, dependencies on unknown settings, dependency cycles, pages of
 * unknown groups and pages whose scope none of the group's settings allow.
 */
final class SettingsDefinitionValidator
{
    /**
     * @return list<string>
     */
    public static function problemsOf(SettingsGroup $group): array
    {
        $problems = [];
        $keys = $group->keys();

        foreach (array_count_values($keys) as $key => $count) {
            if ($count > 1) {
                $problems[] = sprintf('the setting "%s" is declared %d times.', $key, $count);
            }
        }

        foreach (array_count_values(array_map(static fn ($section): string => $section->nodeName(), $group->sections())) as $section => $count) {
            if ($count > 1) {
                $problems[] = sprintf('the section "%s" is declared %d times.', $section, $count);
            }
        }

        foreach ($group->settings() as $setting) {
            foreach (self::settingProblems($setting) as $problem) {
                $problems[] = sprintf('the setting "%s" %s', $setting->key(), $problem);
            }
        }

        return $problems;
    }

    /**
     * @param  list<SettingsGroup>  $groups
     * @param  list<SettingsPage>  $pages
     * @param  array<string, SettingDefinition>  $definitions
     * @return list<string>
     */
    public static function problemsAcross(array $groups, array $pages, array $definitions): array
    {
        $problems = [];
        $owners = [];

        foreach ($groups as $group) {
            foreach (array_unique($group->keys()) as $key) {
                $owners[$key][] = $group->groupName();
            }
        }

        foreach ($owners as $key => $names) {
            if (count($names) > 1) {
                $problems[] = sprintf('The setting "%s" is declared in several groups (%s); setting keys are unique across groups.', $key, implode(', ', $names));
            }
        }

        foreach ($definitions as $key => $definition) {
            foreach ([...$definition->setting->requires(), ...array_keys($definition->setting->requiredWhen())] as $dependency) {
                if (! isset($definitions[$dependency])) {
                    $problems[] = sprintf('The setting "%s" depends on the unknown setting "%s".', $key, $dependency);
                }
            }
        }

        foreach (self::cycles($definitions) as $cycle) {
            $problems[] = sprintf('The settings %s require each other in a cycle.', '"'.implode('" -> "', $cycle).'"');
        }

        $groupNames = array_map(static fn (SettingsGroup $group): string => $group->groupName(), $groups);

        foreach ($pages as $page) {
            if (! in_array($page->groupName(), $groupNames, true)) {
                $problems[] = sprintf('The settings page "%s" shows the unknown group "%s".', $page->pageName(), $page->groupName());

                continue;
            }

            $allowed = array_filter($definitions, static fn (SettingDefinition $definition): bool => $definition->group === $page->groupName() && $definition->allows($page->scope()));

            if ($allowed === []) {
                $problems[] = sprintf('The settings page "%s" edits the %s scope, which no setting of "%s" allows.', $page->pageName(), $page->scope()->value, $page->groupName());
            }
        }

        foreach (array_count_values(array_map(static fn (SettingsPage $page): string => $page->pageName(), $pages)) as $page => $count) {
            if ($count > 1) {
                $problems[] = sprintf('The settings page "%s" is declared %d times.', $page, $count);
            }
        }

        return $problems;
    }

    /**
     * @param  list<string>  $problems
     *
     * @throws InvalidSettingException
     */
    public static function assertNone(array $problems): void
    {
        if ($problems !== []) {
            throw InvalidSettingException::definition('The settings definitions', implode(' ', $problems));
        }
    }

    /**
     * @return list<string>
     */
    private static function settingProblems(Setting $setting): array
    {
        $problems = [];
        $type = $setting->settingType();
        $enum = $setting->enumClass();
        $declaredType = $setting->get(Setting::Type);

        if ($type === SettingType::Enum && $enum === null) {
            $problems[] = 'is an Enum setting without a backed enum class (Setting::Enum).';
        }

        if ($enum !== null && $declaredType !== null && $type !== SettingType::Enum) {
            $problems[] = sprintf('names the enum %s but its type is %s.', $enum, $type->value);
        }

        if ($setting->hasDefault()) {
            $default = $setting->rawDefault();

            if ($default === null && ! $setting->isNullable()) {
                $problems[] = 'has a null default but is not nullable.';
            } elseif ($default !== null) {
                try {
                    (new SettingCaster)->cast($type, $default, $setting->isNullable(), $enum);
                } catch (InvalidSettingException $exception) {
                    $problems[] = sprintf('has a default that is not a valid %s value (%s).', $type->value, $exception->reason ?? 'invalid');
                }
            }
        }

        $options = $setting->options();

        if ($options !== null) {
            if (! in_array($type, [SettingType::String, SettingType::Integer, SettingType::Float], true)) {
                $problems[] = sprintf('declares Options, which only string and number settings support (use an Enum for %s).', $type->value);
            } else {
                foreach (array_keys($options) as $value) {
                    try {
                        (new SettingCaster)->cast($type, $value, false);
                    } catch (InvalidSettingException) {
                        $problems[] = sprintf('has the option "%s", which is not a valid %s value.', $value, $type->value);
                    }
                }

                $default = $setting->rawDefault();

                if ($setting->hasDefault() && $default !== null && ! array_key_exists(is_bool($default) || is_float($default) ? (string) $default : (is_int($default) || is_string($default) ? $default : ''), $options)) {
                    $problems[] = 'has a default that is not one of its Options.';
                }
            }
        }

        if (! in_array($setting->field(), $type->fields(), true) && ! ($setting->field() === 'password' && $setting->isSensitive())) {
            $problems[] = sprintf('uses the field "%s", which cannot edit %s values (use %s).', $setting->field(), $type->value, implode(', ', $type->fields()));
        }

        if (in_array($setting->field(), ['select', 'radio'], true) && $options === null && $type !== SettingType::Enum) {
            $problems[] = sprintf('uses the field "%s" without Options.', $setting->field());
        }

        $scopes = $setting->declaredScopes();
        $precedence = $setting->declaredPrecedence();

        if ($precedence !== null) {
            $allowed = $scopes ?? null;

            if ($allowed !== null && (count($precedence) !== count($allowed) || array_diff(array_map(static fn (SettingScope $scope): string => $scope->value, $precedence), array_map(static fn (SettingScope $scope): string => $scope->value, $allowed)) !== [])) {
                $problems[] = 'declares a Precedence that is not exactly its Scopes.';
            }
        }

        if (in_array($setting->key(), [...$setting->requires(), ...array_keys($setting->requiredWhen())], true)) {
            $problems[] = 'depends on itself.';
        }

        if ($setting->isRequired() && $type === SettingType::Boolean) {
            $problems[] = 'is a Required boolean; a boolean always has a value (use Requires or RequiredWhen for rules between settings).';
        }

        return $problems;
    }

    /**
     * Cycles in the Requires graph.
     *
     * @param  array<string, SettingDefinition>  $definitions
     * @return list<list<string>>
     */
    private static function cycles(array $definitions): array
    {
        $cycles = [];
        $state = [];

        foreach (array_keys($definitions) as $key) {
            if (($state[$key] ?? 0) === 0) {
                self::visit($key, [], $definitions, $state, $cycles);
            }
        }

        return $cycles;
    }

    /**
     * Depth-first search: 1 = on the current path, 2 = done.
     *
     * @param  list<string>  $path
     * @param  array<string, SettingDefinition>  $definitions
     * @param  array<string, int>  $state
     * @param  list<list<string>>  $cycles
     */
    private static function visit(string $key, array $path, array $definitions, array &$state, array &$cycles): void
    {
        $state[$key] = 1;
        $path[] = $key;

        foreach ($definitions[$key]->setting->requires() as $next) {
            if (! isset($definitions[$next])) {
                continue;
            }

            if (($state[$next] ?? 0) === 1) {
                $start = array_search($next, $path, true);
                $cycles[] = [...array_slice($path, is_int($start) ? $start : 0), $next];
            } elseif (($state[$next] ?? 0) === 0) {
                self::visit($next, $path, $definitions, $state, $cycles);
            }
        }

        $state[$key] = 2;
    }
}
