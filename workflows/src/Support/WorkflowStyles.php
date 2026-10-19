<?php

declare(strict_types=1);

namespace Gcego\Workflows\Support;

use Gcego\Chroma\ChromaManager;
use Gcego\Components\Property;
use Gcego\Workflows\Presets\WorkflowPresets;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Workflow;

/**
 * Classes of workflow views: presets merged (gcego/chroma rules) with the
 * application's overrides of the Workflow slots
 * (ChromaManager::override(Workflow::class, new Slot('inbox', '...'))),
 * plus the shared "control.button" presets.
 */
final class WorkflowStyles
{
    /** @var array<string, string> */
    private array $merged = [];

    private ?Workflow $prototype = null;

    public function __construct(private readonly ChromaManager $styling) {}

    /**
     * @param  list<string>  $preset
     */
    public function classes(string $slot, array $preset): string
    {
        $computed = implode(' ', $preset);
        $this->prototype ??= new Workflow(new Property(Workflow::Name, 'prototype'), new State('draft'));
        $own = $this->styling->classes($this->prototype, $slot);

        return $this->merged[$slot."\0".$computed] ??= $own === '' ? $computed : $this->styling->merge($computed, $own);
    }

    public function button(string $intent = 'secondary'): string
    {
        return $this->merged['button.'.$intent] ??= $this->styling->merge($this->styling->preset('control.button'), $this->styling->preset('control.button.'.$intent));
    }

    public function tone(string $color): string
    {
        $tone = WorkflowPresets::Tones[$color] ?? WorkflowPresets::Tones['neutral'];

        return implode(' ', [...WorkflowPresets::Badge, ...$tone]);
    }
}
