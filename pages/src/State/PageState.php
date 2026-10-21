<?php

declare(strict_types=1);

namespace Gcego\Pages\State;

use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Properties\Property;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Layouts\Components\Tab;
use Gcego\Layouts\Components\Tabs;
use Gcego\Pages\Page;

/**
 * The small, browser-visible state of a page besides its identity and
 * record key: the tab to open first (?tab=results deep links to the
 * "results" tab of the page's tab sets).
 *
 * Input is untrusted: a tab that the page does not declare is dropped.
 * Nothing here is a model, a class name or an authorization decision.
 */
final readonly class PageState
{
    public function __construct(public ?string $tab = null) {}

    /**
     * @param  array<array-key, mixed>  $input  Untrusted (query string or Livewire state).
     */
    public static function from(Page $page, array $input): self
    {
        $tab = $input['tab'] ?? null;

        return new self(is_string($tab) && strlen($tab) <= 64 && in_array($tab, self::tabs($page), true) ? $tab : null);
    }

    /**
     * The tab names declared anywhere in the page.
     *
     * @return list<string>
     */
    public static function tabs(Page $page): array
    {
        $names = [];

        foreach (app(ComponentWalker::class)->preOrder($page) as $node) {
            if ($node->component instanceof Tab) {
                $names[] = $node->component->tabName();
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The page to render for this state: a copy whose tab sets open the
     * chosen tab (same structure and paths, so every part keeps its identity).
     */
    public function apply(Page $page): Page
    {
        if ($this->tab === null) {
            return $page;
        }

        $applied = self::open($page, $this->tab);

        return $applied instanceof Page ? $applied : $page;
    }

    /**
     * @return array{tab: string|null}
     */
    public function toArray(): array
    {
        return ['tab' => $this->tab];
    }

    private static function open(ComponentInterface $node, string $tab): ComponentInterface
    {
        if (! $node instanceof Component) {
            return $node;
        }

        if ($node instanceof Tabs) {
            foreach ($node->children() as $child) {
                if ($child instanceof Tab && $child->tabName() === $tab) {
                    return $node->withProperty(new Property(Tabs::Active, $tab));
                }
            }
        }

        $changed = false;
        $children = [];

        foreach ($node->children() as $child) {
            $opened = self::open($child, $tab);
            $changed = $changed || $opened !== $child;
            $children[] = $opened;
        }

        return $changed ? $node->withChildren(...$children) : $node;
    }
}
