<?php

declare(strict_types=1);

namespace Gcego\Panels\Registry;

use Gcego\Panels\Exceptions\PanelNotFoundException;
use Gcego\Panels\Panel;
use Gcego\Registry\Contracts\RegistryInterface;
use Throwable;

/**
 * Registered panels, by name. Descriptors come from the registry index
 * (cheap, no definition loaded); definitions are loaded on demand.
 * Names are validated before any lookup, so request input never reaches
 * the registry unchecked.
 */
final readonly class PanelDefinitions
{
    public function __construct(private RegistryInterface $registry) {}

    /**
     * Every panel, by Order then name.
     *
     * @return list<PanelDescriptor>
     */
    public function descriptors(): array
    {
        $panels = array_map(PanelDescriptor::fromDescriptor(...), $this->registry->descriptors(PanelKind::Id));
        usort($panels, static fn (PanelDescriptor $a, PanelDescriptor $b): int => [$a->order, $a->name] <=> [$b->order, $b->name]);

        return $panels;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (PanelDescriptor $panel): string => $panel->name, $this->descriptors());
    }

    public function has(string $name): bool
    {
        return self::valid($name) && $this->registry->has(PanelKind::Id.':'.$name);
    }

    /**
     * @throws PanelNotFoundException
     */
    public function descriptor(string $name): PanelDescriptor
    {
        if (! $this->has($name)) {
            throw PanelNotFoundException::named($name);
        }

        return PanelDescriptor::fromDescriptor($this->registry->descriptor(PanelKind::Id.':'.$name));
    }

    /**
     * @throws PanelNotFoundException
     */
    public function resolve(string $name): Panel
    {
        if (! $this->has($name)) {
            throw PanelNotFoundException::named($name);
        }

        try {
            $panel = $this->registry->get(PanelKind::Id.':'.$name);
        } catch (Throwable) {
            throw PanelNotFoundException::named($name);
        }

        return $panel instanceof Panel ? $panel : throw PanelNotFoundException::named($name);
    }

    public static function valid(string $name): bool
    {
        return strlen($name) <= 50 && preg_match(Panel::NamePattern, $name) === 1;
    }
}
