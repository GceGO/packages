<?php

declare(strict_types=1);

namespace Gcego\Vault\Integration;

use Gcego\Components\Properties\Property;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Contracts\MultipleNavigationsContributor;
use Gcego\Panels\PanelsConfig;
use Gcego\Panels\Registry\PanelDefinitions;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Vault\Requirements\VaultAccess;
use Gcego\Vault\VaultConfig;

/**
 * Adds the "Access control" group to each panel's navigation. Every item
 * carries the requirement of its screen (VaultAccess), so gcego/navigation
 * shows it exactly to people who may open it; the route enforces the same
 * requirement. Structure only (cached per process, evaluated per user).
 */
final readonly class VaultNavigationContributor implements MultipleNavigationsContributor
{
    public function __construct(private PanelDefinitions $panels, private PanelsConfig $panelsConfig, private VaultConfig $config) {}

    public function navigation(): string
    {
        return 'panel-none';
    }

    public function nodes(): iterable
    {
        return [];
    }

    public function contributesTo(string $identity): bool
    {
        return $this->panelsOf($identity) !== [];
    }

    public function nodesFor(string $identity): iterable
    {
        $nodes = [];

        foreach ($this->panelsOf($identity) as $panel) {
            $namespace = $panel->routeNamespace($this->panelsConfig->namePrefix);
            $items = [];
            $order = 0;

            foreach (Screens::all() as $name => $screen) {
                if (! $screen['nav'] || ($name === 'requests' && ! $this->config->requests)) {
                    continue;
                }

                $items[] = new Item(
                    new Property(Item::Name, 'vault-'.$name),
                    new Property(Item::Label, (string) __('gcego-vault::messages.screens.'.$screen['label'])),
                    new Property(Item::Route, $namespace.'vault.'.$name),
                    new Property(Item::Icon, $screen['icon']),
                    new Property(Item::Order, $order++),
                    new Property(Item::Active, $name === 'roles' ? [$namespace.'vault.roles', $namespace.'vault.roles.*'] : ($name === 'people' ? [$namespace.'vault.people', $namespace.'vault.people.*'] : [$namespace.'vault.'.$name])),
                    $screen['permission'] === null ? new Authenticated : new VaultAccess($screen['permission']),
                );
            }

            $nodes[] = new Group($this->config->navigationGroup, new Property(Group::Name, 'vault'), new Property(Group::Order, 9000), ...$items);
        }

        return $nodes;
    }

    /**
     * @return list<PanelDescriptor>
     */
    private function panelsOf(string $identity): array
    {
        if (! $this->config->enabled) {
            return [];
        }

        return array_values(array_filter($this->panels->descriptors(), fn (PanelDescriptor $panel): bool => 'navigation:'.$panel->navigation === $identity && $this->config->showsIn($panel->name)));
    }
}
