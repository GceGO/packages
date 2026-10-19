<?php

declare(strict_types=1);

namespace Gcego\Settings\Registry;

use Gcego\Components\Validation\ValidationError;
use Gcego\Registry\Contracts\DefinitionKind;
use Gcego\Settings\SettingsGroup;
use InvalidArgumentException;

/**
 * The "settings" kind of gcego/registry ("settings:general"), discovered
 * from app/GCEGo/Settings: every PHP file returns a SettingsGroup, or the
 * name of a DefinitionProvider class returning several.
 */
final readonly class SettingsKind implements DefinitionKind
{
    public const string Id = 'settings';

    public function id(): string
    {
        return self::Id;
    }

    public function contract(): string
    {
        return SettingsGroup::class;
    }

    public function name(object $definition): string
    {
        $name = $this->group($definition)->groupName();

        if (! SettingsGroup::validName($name)) {
            throw new InvalidArgumentException('A settings group needs a Name such as "general".');
        }

        return $name;
    }

    public function validate(object $definition): array
    {
        return array_map(static fn (ValidationError $error): string => $error->format(), $this->group($definition)->validate()->errors());
    }

    public function metadata(object $definition): array
    {
        $group = $this->group($definition);

        return ['title' => $group->title(), 'settings' => count($group->settings()), 'sections' => count($group->sections())];
    }

    private function group(object $definition): SettingsGroup
    {
        return $definition instanceof SettingsGroup ? $definition : throw new InvalidArgumentException('Not a settings group.');
    }
}
