<?php

declare(strict_types=1);

namespace Gcego\Settings\Pages;

use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\SettingsGroup;
use Gcego\Settings\SettingsPage;

/**
 * An opened settings page for one viewer: its group, the server-resolved
 * context it edits, and the settings that viewer may see (visible) and
 * change (editable keys). Request-local; never serialized.
 */
final readonly class SettingsScreen
{
    /**
     * @param  list<SettingDefinition>  $visible
     * @param  array<string, bool>  $editable
     */
    public function __construct(
        public SettingsPage $page,
        public SettingsGroup $group,
        public ScopeContext $context,
        public array $visible,
        public array $editable,
    ) {}

    public function canEdit(string $key): bool
    {
        return $this->editable[$key] ?? false;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(static fn (SettingDefinition $definition): string => $definition->key, $this->visible);
    }
}
