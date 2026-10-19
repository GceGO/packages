<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Navigation\Breadcrumbs\Breadcrumb;
use Gcego\Navigation\NavigationManager;
use Gcego\Navigation\Resolution\NavigationResolver;
use Gcego\Navigation\Resolution\ResolvedNavigation;
use Gcego\Navigation\Resolution\ResolvedNode;
use Gcego\Navigation\Resolution\RouteResolver;
use Gcego\Navigation\Resolution\VisibilityResolver;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Support\Reports;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;

/**
 * A gcego/navigation navigation as the acting user sees it on a page:
 * which items are visible (requirements re-evaluated), which one is active,
 * their URLs, badges and the breadcrumb trail.
 */
final class NavigationTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly Container $container, private readonly TestEnvironment $environment, private readonly string $navigation)
    {
        parent::__construct($verifier);
    }

    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $tenant, $guard);

        return $this;
    }

    /**
     * Resolves the navigation for this URI (the route is matched and
     * bound), as when that page is rendered.
     */
    public function on(string $uri): self
    {
        $this->environment->onPage($uri, [NavigationResolver::class, RouteResolver::class, VisibilityResolver::class]);

        return $this;
    }

    public function resolved(): ResolvedNavigation
    {
        return $this->manager()->resolved($this->navigation);
    }

    public function assertVisible(string $id): self
    {
        return $this->check($this->resolved()->find($id) !== null, sprintf('assertVisible(%s)', $this->export($id)), sprintf('Expected the navigation item [%s] to be visible.', $id), ['visible' => true], ['visible items' => $this->ids()], 'Items are hidden when a requirement of the item or an ancestor refuses the user, or when their route has no URL here.');
    }

    public function assertHidden(string $id): self
    {
        return $this->check($this->resolved()->find($id) === null, sprintf('assertHidden(%s)', $this->export($id)), sprintf('Expected the navigation item [%s] to be hidden.', $id), ['visible' => false], ['visible items' => $this->ids()]);
    }

    public function assertActive(string $id): self
    {
        $active = $this->resolved()->active?->id;

        return $this->check($active === $id, sprintf('assertActive(%s)', $this->export($id)), sprintf('Expected [%s] to be the active item.', $id), ['active' => $id], ['active' => $active]);
    }

    public function assertNoActive(): self
    {
        $active = $this->resolved()->active?->id;

        return $this->check($active === null, 'assertNoActive()', 'Expected no active item.', ['active' => null], ['active' => $active]);
    }

    public function assertUrl(string $id, ?string $url): self
    {
        $actual = $this->resolved()->find($id)?->url;

        return $this->check($actual === $url, sprintf('assertUrl(%s, %s)', $this->export($id), $this->export($url)), sprintf('Expected the URL of [%s].', $id), ['url' => $url], ['url' => $actual]);
    }

    public function assertBadge(string $id, ?string $text): self
    {
        $actual = $this->resolved()->find($id)?->badge?->text;

        return $this->check($actual === $text, sprintf('assertBadge(%s, %s)', $this->export($id), $this->export($text)), sprintf('Expected the badge of [%s].', $id), ['badge' => $text], ['badge' => $actual]);
    }

    /**
     * @param  list<string>  $labels
     */
    public function assertBreadcrumbs(array $labels): self
    {
        $actual = array_map(static fn (Breadcrumb $crumb): string => $crumb->label, $this->manager()->breadcrumbs($this->navigation)->items);

        return $this->check($actual === $labels, 'assertBreadcrumbs('.$this->export($labels).')', 'Expected this breadcrumb trail.', ['breadcrumbs' => $labels], ['breadcrumbs' => $actual]);
    }

    public function html(?string $display = null): HtmlTestable
    {
        return new HtmlTestable($this->verifier, $this->manager()->render($this->manager()->identity($this->navigation), $display)->toHtml(), 'navigation');
    }

    public function assertHealthy(): self
    {
        $problems = Reports::problems($this->manager()->inspect($this->navigation));

        return $this->check($problems === [], 'assertHealthy()', 'Expected the navigation inspector to report no errors.', ['errors' => []], ['errors' => $problems]);
    }

    protected function subject(): string
    {
        return 'navigation';
    }

    protected function context(): array
    {
        $request = $this->container->bound('request') ? $this->container->make('request') : null;

        return ['Navigation' => $this->navigation, 'Page' => is_object($request) && method_exists($request, 'path') ? '/'.ltrim((string) $request->path(), '/') : '(none)'];
    }

    /**
     * @return list<string>
     */
    private function ids(): array
    {
        $ids = [];
        $walk = static function (array $nodes) use (&$walk, &$ids): void {
            foreach ($nodes as $node) {
                if ($node instanceof ResolvedNode) {
                    $ids[] = $node->id;
                    $walk($node->children);
                }
            }
        };
        $walk($this->resolved()->nodes);

        return $ids;
    }

    private function manager(): NavigationManager
    {
        return $this->container->make(NavigationManager::class);
    }
}
