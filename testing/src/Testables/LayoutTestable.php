<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Components\ComponentTree;
use Gcego\Layouts\Components\Tab;
use Gcego\Layouts\Layout;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\LayoutManager;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Support\Reports;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;

/**
 * A gcego/layouts layout, registered ("dashboard", "layout:dashboard") or
 * given as a component: structure, inspector findings and rendering.
 */
final class LayoutTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly Container $container, private readonly TestEnvironment $environment, private readonly string|LayoutComponent $layout)
    {
        parent::__construct($verifier);
    }

    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $tenant, $guard);

        return $this;
    }

    public function component(): LayoutComponent
    {
        return is_string($this->layout) ? $this->manager()->resolve($this->layout) : $this->layout;
    }

    public function assertHealthy(): self
    {
        $problems = Reports::problems($this->manager()->inspect($this->layout));

        return $this->check($problems === [], 'assertHealthy()', 'Expected the layout inspector to report no errors.', ['errors' => []], ['errors' => $problems]);
    }

    /**
     * The inspector reports this check (e.g. "nesting", "spans",
     * "accessibility") at warning or error level.
     */
    public function assertReports(string $check): self
    {
        $problems = Reports::problems($this->manager()->inspect($this->layout), 'warning');
        $matching = array_filter($problems, static fn (string $problem): bool => str_starts_with($problem, $check.':'));

        return $this->check($matching !== [], sprintf('assertReports(%s)', $this->export($check)), sprintf('Expected the layout inspector to report [%s].', $check), ['check' => $check], ['reported' => $problems]);
    }

    /**
     * A component of this class (or type) appears in the layout.
     */
    public function assertContains(string $class, ?int $times = null): self
    {
        $this->components()->assertContainsType($class, $times);

        return $this;
    }

    /**
     * The layout's tabs, in order (tab names).
     *
     * @param  list<string>  $names
     */
    public function assertTabs(array $names): self
    {
        $tabs = [];

        foreach ((new ComponentTree($this->component()))->findByType(Tab::class) as $node) {
            if ($node->component instanceof Tab) {
                $tabs[] = $node->component->tabName();
            }
        }

        return $this->check($tabs === $names, 'assertTabs('.$this->export($names).')', 'Expected these tabs in order.', ['tabs' => $names], ['tabs' => $tabs]);
    }

    public function assertAllowed(): self
    {
        return $this->check($this->allows(), 'assertAllowed()', 'Expected the acting user to be allowed to see the layout.', ['allowed' => true], ['allowed' => false]);
    }

    public function assertDenied(): self
    {
        return $this->check(! $this->allows(), 'assertDenied()', 'Expected the acting user to be refused the layout.', ['allowed' => false], ['allowed' => true]);
    }

    /**
     * Renders a registered layout with authorization, or a given component
     * as trusted content.
     */
    public function html(string|int|null $record = null): HtmlTestable
    {
        $html = is_string($this->layout) ? $this->manager()->render($this->manager()->identity($this->layout), $record) : $this->manager()->renderLayout($this->layout, record: $record);

        return new HtmlTestable($this->verifier, $html->toHtml(), 'layout');
    }

    public function components(): ComponentTestable
    {
        return new ComponentTestable($this->verifier, $this->component());
    }

    protected function subject(): string
    {
        return 'layout';
    }

    protected function context(): array
    {
        return ['Layout' => is_string($this->layout) ? $this->layout : $this->layout::class];
    }

    private function allows(): bool
    {
        $layout = $this->component();

        return $layout instanceof Layout && $this->manager()->allows($layout);
    }

    private function manager(): LayoutManager
    {
        return $this->container->make(LayoutManager::class);
    }
}
