<?php

declare(strict_types=1);

namespace Gcego\Settings\Definitions;

use BackedEnum;
use Gcego\Security\Contracts\Requirement;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\Setting;
use Gcego\Settings\Types\SettingType;

/**
 * The one internal form of a setting, whichever style declared it: its
 * group and section, effective type, typed default, scopes and their
 * lookup order, and the requirements of the setting, its section and its
 * group together. Built by SettingsDefinitions; immutable.
 */
final readonly class SettingDefinition
{
    /**
     * @param  class-string<BackedEnum>|null  $enum
     * @param  array<array-key, string>|null  $options
     * @param  list<string>  $rules
     * @param  list<SettingScope>  $scopes
     * @param  list<SettingScope>  $precedence  Most specific first; only allowed scopes.
     * @param  list<Requirement>  $requirements
     */
    public function __construct(
        public Setting $setting,
        public string $key,
        public string $group,
        public ?string $section,
        public SettingType $type,
        public bool $nullable,
        public bool $required,
        public bool $hasDefault,
        public mixed $default,
        public ?string $enum,
        public ?array $options,
        public array $rules,
        public array $scopes,
        public array $precedence,
        public bool $sensitive,
        public string $field,
        public ?string $approval,
        public array $requirements,
        public bool $hidden,
    ) {}

    public function label(): string
    {
        return (string) __($this->setting->label());
    }

    public function description(): ?string
    {
        $description = $this->setting->description();

        return $description === null ? null : (string) __($description);
    }

    public function allows(SettingScope $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    /**
     * Sensitive values are always encrypted at rest.
     */
    public function encrypted(): bool
    {
        return $this->sensitive;
    }
}
