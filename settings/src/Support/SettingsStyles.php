<?php

declare(strict_types=1);

namespace Gcego\Settings\Support;

use Gcego\Chroma\ChromaManager;
use Gcego\Settings\Presets\SettingsPresets;
use Gcego\Settings\SettingsGroup;

/**
 * Classes of settings views: presets merged (gcego/chroma rules) with the
 * application's overrides of the SettingsGroup slots
 * (ChromaManager::override(SettingsGroup::class, new Slot('wrapper', '...'))),
 * plus the shared "control.button" presets.
 */
final class SettingsStyles
{
    /** @var array<string, string> */
    private array $merged = [];

    private ?SettingsGroup $prototype = null;

    public function __construct(private readonly ChromaManager $styling) {}

    /**
     * @param  list<string>  $preset
     */
    public function classes(string $slot, array $preset): string
    {
        $computed = implode(' ', $preset);
        $this->prototype ??= new SettingsGroup('prototype');
        $own = $this->styling->classes($this->prototype, $slot);

        return $this->merged[$slot."\0".$computed] ??= $own === '' ? $computed : $this->styling->merge($computed, $own);
    }

    public function button(string $intent = 'secondary'): string
    {
        return $this->merged['button.'.$intent] ??= $this->styling->merge($this->styling->preset('control.button'), $this->styling->preset('control.button.'.$intent));
    }

    /**
     * @param  list<string>  $tone
     */
    public function badge(array $tone): string
    {
        return implode(' ', [...SettingsPresets::Badge, ...$tone]);
    }
}
