<?php

declare(strict_types=1);

namespace Gcego\Navigation\Registry;

use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Navigation\Navigation;
use Gcego\Registry\Contracts\DefinitionKind;
use InvalidArgumentException;

/**
 * The "navigation" definition kind of gcego/registry: navigations are
 * registered and discovered (app/GCEGo/Navigation) under
 * "navigation:<name>". Clients refer to them by that identity only.
 */
final readonly class NavigationKind implements DefinitionKind
{
    public const string Id = 'navigation';

    public function id(): string
    {
        return self::Id;
    }

    public function contract(): string
    {
        return Navigation::class;
    }

    public function name(object $definition): string
    {
        return $this->navigation($definition)->navigationName();
    }

    public function validate(object $definition): array
    {
        $navigation = $this->navigation($definition);
        $errors = array_map(strval(...), $navigation->validate()->errors());

        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $navigation->navigationName()) !== 1) {
            $errors[] = 'A navigation needs a Name such as "admin" (new Navigation(\'admin\', …)).';
        }

        return $errors;
    }

    public function metadata(object $definition): array
    {
        $navigation = $this->navigation($definition);
        $nodes = -1;

        foreach ((new ComponentWalker)->preOrder($navigation) as $node) {
            $nodes++;
        }

        return ['label' => $navigation->label(), 'display' => $navigation->display(), 'nodes' => $nodes];
    }

    private function navigation(object $definition): Navigation
    {
        return $definition instanceof Navigation ? $definition : throw new InvalidArgumentException(sprintf('%s is not a navigation.', $definition::class));
    }
}
